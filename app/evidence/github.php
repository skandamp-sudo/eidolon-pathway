<?php
declare(strict_types=1);
namespace Pathway\Evidence;

final class EvidenceError extends \RuntimeException
{
    public function __construct(public readonly string $reason) { parent::__construct($reason); }
}

function repository(string $input): string
{
    if (strlen($input) > 180) { throw new EvidenceError('invalid_repository'); }
    $input = preg_replace('~\Ahttps://github\.com/~', '', trim($input));
    $input = rtrim($input, '/');
    if (!preg_match('~\A([A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?)/([A-Za-z0-9_.-]{1,100})\z~D', $input, $match) ||
        in_array($match[2], ['.', '..'], true) || str_ends_with($match[2], '.git')) {
        throw new EvidenceError('invalid_repository');
    }
    return strtolower($input);
}

function role(string $input): string
{
    if (!in_array($input, ['frontend', 'backend', 'fullstack'], true)) { throw new EvidenceError('invalid_role'); }
    return $input;
}

function sha(mixed $input): string
{
    if (!is_string($input) || !preg_match('/\A[a-f0-9]{40}\z/D', $input)) { throw new EvidenceError('invalid_github_response'); }
    return $input;
}

interface Transport
{
    /** @return array{status:int,headers:array,body:string} */
    public function fetch(string $resource, int $limit, float $deadline): array;
}

final class CurlTransport implements Transport
{
    public function fetch(string $resource, int $limit, float $deadline): array
    {
        if (!preg_match('~\A/repos/[A-Za-z0-9-]+/[A-Za-z0-9_.-]+(?:/commits\?per_page=1|/git/trees/[a-f0-9]{40}\?recursive=1|/git/blobs/[a-f0-9]{40})?\z~D', $resource)) {
            throw new EvidenceError('invalid_transport_path');
        }
        $remaining = min(8.0, $deadline - microtime(true));
        if ($remaining <= 0) { throw new EvidenceError('deadline_exceeded'); }
        $raw = str_contains($resource, '/git/blobs/');
        // Array invocation: no shell. -q disables curlrc. No ambient auth/proxy/config.
        $args = ['/usr/bin/curl', '-q', '--silent', '--show-error', '--include', '--proto', '=https',
            '--max-redirs', '0', '--proxy', '', '--noproxy', '*', '--connect-timeout', '3',
            '--max-time', sprintf('%.3f', $remaining), '--max-filesize', (string) $limit,
            '--header', 'Accept-Encoding: identity', '--header', 'User-Agent: Eidolon-Pathway-Evidence/1',
            '--header', 'X-GitHub-Api-Version: 2026-03-10',
            '--header', 'Accept: ' . ($raw ? 'application/vnd.github.raw+json' : 'application/vnd.github+json'),
            '--url', 'https://api.github.com' . $resource];
        $process = proc_open($args, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes,
            null, ['PATH' => '/usr/bin:/bin', 'LANG' => 'C']);
        if (!is_resource($process)) { throw new EvidenceError('transport_unavailable'); }
        stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
        $wire = ''; $stderr = ''; $exit = -1;
        try {
            while (true) {
                $wire .= stream_get_contents($pipes[1], $limit + 16385 - strlen($wire));
                $stderr .= stream_get_contents($pipes[2], max(1, 2049 - strlen($stderr)));
                if (strlen($wire) > $limit + 16384 || strlen($stderr) > 2048) { throw new EvidenceError('response_too_large'); }
                if (microtime(true) > $deadline) { throw new EvidenceError('deadline_exceeded'); }
                $status = proc_get_status($process);
                if (!$status['running']) {
                    $exit = $status['exitcode'];
                    $wire .= stream_get_contents($pipes[1], $limit + 16385 - strlen($wire));
                    break;
                }
                $read = [$pipes[1], $pipes[2]]; $write = null; $except = null;
                stream_select($read, $write, $except, 0, 100000);
            }
        } finally {
            if (proc_get_status($process)['running']) { proc_terminate($process, 9); }
            fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
        }
        if ($exit === 63) { throw new EvidenceError('response_too_large'); }
        if ($exit !== 0) { throw new EvidenceError('network_unavailable'); }
        $separator = strpos($wire, "\r\n\r\n");
        if ($separator === false || $separator > 16384) { throw new EvidenceError('invalid_github_response'); }
        $head = substr($wire, 0, $separator); $body = substr($wire, $separator + 4);
        if (strlen($body) > $limit) { throw new EvidenceError('response_too_large'); }
        if (!preg_match('~\AHTTP/[0-9.]+ ([0-9]{3})~', $head, $match)) { throw new EvidenceError('invalid_github_response'); }
        $headers = [];
        foreach (explode("\r\n", $head) as $line) {
            if (str_contains($line, ':')) { [$key, $value] = explode(':', $line, 2); $headers[strtolower($key)] = trim($value); }
        }
        return ['status' => (int) $match[1], 'headers' => $headers, 'body' => $body];
    }
}

final class GitHub
{
    public int $requests = 0;
    private int $bytes = 0;
    private bool $retried = false;
    private float $deadline;

    public function __construct(private Transport $transport, private ?\Closure $reserve = null, private ?\Closure $cooldown = null)
    {
        $this->deadline = microtime(true) + 45;
    }

    public function get(string $resource, int $limit = 524288): array|string
    {
        $raw = str_contains($resource, '/git/blobs/');
        while (true) {
            if ($this->requests >= 10 || $this->bytes >= 1572864) { throw new EvidenceError('request_budget_exceeded'); }
            if (microtime(true) >= $this->deadline) { throw new EvidenceError('deadline_exceeded'); }
            if ($this->reserve) { ($this->reserve)(); }
            $this->requests++;
            try { $response = $this->transport->fetch($resource, min($limit, 1572864 - $this->bytes), $this->deadline); }
            catch (EvidenceError $error) {
                if ($error->reason === 'network_unavailable' && !$this->retried) { $this->retried = true; continue; }
                throw $error;
            }
            $body = $response['body'];
            if (strlen($body) > $limit || strlen($body) + $this->bytes > 1572864) { throw new EvidenceError('response_too_large'); }
            $this->bytes += strlen($body);
            $status = $response['status']; $headers = $response['headers'];
            if ($status === 429 || $status === 403 || (($headers['x-ratelimit-remaining'] ?? null) === '0')) {
                $until = time() + 3600;
                if (isset($headers['x-ratelimit-reset']) && ctype_digit($headers['x-ratelimit-reset'])) {
                    $until = max($until, min(time() + 86400, (int) $headers['x-ratelimit-reset']));
                }
                if (isset($headers['retry-after']) && ctype_digit($headers['retry-after'])) {
                    $until = max($until, time() + min(86400, (int) $headers['retry-after']));
                }
                if ($this->cooldown) { ($this->cooldown)($until); }
                throw new EvidenceError('rate_limited');
            }
            if ($status >= 500 && $status <= 599 && !$this->retried) { $this->retried = true; continue; }
            if ($status === 404) { throw new EvidenceError('public_repository_unavailable'); }
            if ($status !== 200) { throw new EvidenceError('github_request_rejected'); }
            if ($raw) { return $body; }
            try { $json = json_decode($body, true, 32, JSON_THROW_ON_ERROR); }
            catch (\JsonException) { throw new EvidenceError('invalid_github_response'); }
            if (!is_array($json)) { throw new EvidenceError('invalid_github_response'); }
            return $json;
        }
    }

    public function pin(string $repository): array
    {
        $base = '/repos/' . repository($repository);
        $metadata = $this->get($base);
        if (($metadata['private'] ?? null) !== false || ($metadata['visibility'] ?? '') !== 'public' ||
            strtolower((string) ($metadata['full_name'] ?? '')) !== $repository) {
            throw new EvidenceError('public_repository_unavailable');
        }
        $commits = $this->get($base . '/commits?per_page=1');
        if (count($commits) !== 1) { throw new EvidenceError('empty_repository'); }
        return ['commit' => sha($commits[0]['sha'] ?? null), 'tree' => sha($commits[0]['commit']['tree']['sha'] ?? null)];
    }

    public function tree(string $repository, string $tree): array
    {
        $data = $this->get('/repos/' . $repository . '/git/trees/' . sha($tree) . '?recursive=1');
        if (($data['truncated'] ?? null) !== false || sha($data['sha'] ?? null) !== $tree ||
            !isset($data['tree']) || !is_array($data['tree']) || count($data['tree']) > 2000) {
            throw new EvidenceError('tree_limit_exceeded');
        }
        return $data['tree'];
    }

    public function file(string $repository, array $entry): string
    {
        $body = $this->get('/repos/' . $repository . '/git/blobs/' . sha($entry['sha']), 32768);
        if (!is_string($body) || strlen($body) !== $entry['size'] ||
            !hash_equals($entry['sha'], sha1('blob ' . strlen($body) . "\0" . $body))) {
            throw new EvidenceError('blob_integrity_mismatch');
        }
        if (str_contains($body, "\0") || !preg_match('//u', $body)) { throw new EvidenceError('unsupported_file_encoding'); }
        return $body;
    }
}
