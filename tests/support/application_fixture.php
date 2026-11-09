<?php

// CLI-only adapter: runs production prepared SQL against disposable SQLite memory.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

class ApplicationFixtureResult
{
    public $num_rows;
    private $rows;

    public function __construct($rows)
    {
        $this->rows = $rows;
        $this->num_rows = count($rows);
    }

    public function fetch_assoc()
    {
        return array_shift($this->rows);
    }
}

class ApplicationFixtureStatement
{
    public $affected_rows = 0;
    private $fixture;
    private $sql;
    private $values = [];
    private $statement;

    public function __construct($fixture, $sql)
    {
        $this->fixture = $fixture;
        $this->sql = $sql;
    }

    public function bind_param($types, &...$values)
    {
        if (strlen($types) !== count($values) || substr_count($this->sql, '?') !== count($values)) {
            throw new RuntimeException('Fixture parameter count mismatch.');
        }
        $this->values = $values;
        return true;
    }

    public function execute()
    {
        if ($this->fixture->failExecute) {
            return false;
        }
        if (preg_match('/^\s*UPDATE\b/', $this->sql) && $this->fixture->beforeUpdate) {
            $callback = $this->fixture->beforeUpdate;
            $this->fixture->beforeUpdate = null;
            $callback($this->fixture->pdo);
        }
        $this->statement = $this->fixture->pdo->prepare($this->sql);
        foreach ($this->values as $index => $value) {
            $this->statement->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $ok = $this->statement->execute();
        $this->affected_rows = $this->statement->rowCount();
        return $ok;
    }

    public function get_result()
    {
        return new ApplicationFixtureResult($this->statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function close()
    {
        if ($this->statement) {
            $this->statement->closeCursor();
        }
    }
}

class ApplicationFixture
{
    public $pdo;
    public $failPrepare = false;
    public $failExecute = false;
    public $beforeUpdate;

    public function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, role TEXT);
            CREATE TABLE jobs (id INTEGER PRIMARY KEY, company_id INTEGER, title TEXT);
            CREATE TABLE applications (id INTEGER PRIMARY KEY, student_id INTEGER, job_id INTEGER, status TEXT);
            INSERT INTO users VALUES
                (1, 'Fixture Company A', 'a@example.test', 'company'),
                (2, 'Fixture Company B', 'b@example.test', 'company'),
                (3, '<script>alert(1)</script>', 'student@example.test', 'student');
            INSERT INTO jobs VALUES (10, 1, 'Company A Job'), (20, 2, 'Company B Job');
            INSERT INTO applications VALUES (100, 3, 10, 'applied'), (200, 3, 20, 'applied');
        ");
    }

    public function prepare($sql)
    {
        return $this->failPrepare ? false : new ApplicationFixtureStatement($this, $sql);
    }

    public function statuses()
    {
        return $this->pdo->query('SELECT id, status FROM applications ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
