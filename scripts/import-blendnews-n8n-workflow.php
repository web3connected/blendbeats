<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "[FAIL] This script must be run from the command line.\n");
    exit(1);
}

$root = dirname(__DIR__);
$workflowPath = $root.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'blendnews-rss-draft-intake.n8n.workflow.json';
$dryRun = in_array('--dry-run', $argv, true);

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    echo "Usage: php scripts/import-blendnews-n8n-workflow.php [--dry-run]\n";
    echo "\n";
    echo "Required environment variables for import:\n";
    echo "  N8N_URL\n";
    echo "  N8N_API_KEY\n";
    exit(0);
}

$autoloadPath = $root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

if (is_file($autoloadPath)) {
    require $autoloadPath;

    if (class_exists(\Dotenv\Dotenv::class) && is_file($root.DIRECTORY_SEPARATOR.'.env')) {
        \Dotenv\Dotenv::createImmutable($root)->safeLoad();
    }
}

function failImport(string $message, int $code = 1): never
{
    fwrite(STDERR, "[FAIL] {$message}\n");
    exit($code);
}

function successLine(string $message): void
{
    echo "[OK] {$message}\n";
}

function infoLine(string $message): void
{
    echo "[INFO] {$message}\n";
}

function requiredEnvValue(string $key): string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || trim((string) $value) === '') {
        failImport("Missing required environment variable: {$key}");
    }

    return trim((string) $value, " \t\n\r\0\x0B\"'");
}

function normalizeN8nUrl(string $url): string
{
    $url = rtrim($url, '/');

    if (! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('/^https?:\/\//i', $url)) {
        failImport('N8N_URL must be a valid http or https URL.');
    }

    return $url;
}

function scanWorkflowForSecrets(string $json): array
{
    $issues = [];
    $allowedPlaceholders = [
        '<APP_URL>',
        '<AUTOMATION_API_TOKEN>',
        '<RSS_FEED_URL>',
    ];

    if (preg_match_all('/<[^>\r\n]+>/', $json, $matches)) {
        foreach (array_unique($matches[0]) as $placeholder) {
            if (! in_array($placeholder, $allowedPlaceholders, true)) {
                $issues[] = "Unexpected placeholder or inline value found: {$placeholder}";
            }
        }
    }

    $patterns = [
        'OpenAI-style API key' => '/sk-[A-Za-z0-9_-]{20,}/',
        'GitHub classic token' => '/ghp_[A-Za-z0-9_]{20,}/',
        'GitHub fine-grained token' => '/github_pat_[A-Za-z0-9_]{20,}/',
        'Slack token' => '/xox[baprs]-[A-Za-z0-9-]{20,}/',
        'AWS access key' => '/AKIA[0-9A-Z]{16}/',
        'real bearer token' => '/Bearer\s+(?!<AUTOMATION_API_TOKEN>)[A-Za-z0-9._~+\-\/=]{20,}/i',
        'real n8n API key header' => '/X-N8N-API-KEY\s*[:=]\s*[A-Za-z0-9._~+\-\/=]{20,}/i',
        'real HTTP URL' => '/https?:\/\/[^\s"<>]+/i',
    ];

    foreach ($patterns as $label => $pattern) {
        if (preg_match($pattern, $json)) {
            $issues[] = "Possible {$label} detected in workflow JSON.";
        }
    }

    return $issues;
}

function jsonPreview(string $body): string
{
    $body = trim($body);

    if ($body === '') {
        return '(empty response)';
    }

    return mb_substr($body, 0, 800);
}

function buildN8nCreatePayload(stdClass $workflow): stdClass
{
    foreach (['name', 'nodes', 'connections'] as $requiredField) {
        if (! property_exists($workflow, $requiredField)) {
            failImport("Workflow JSON is missing required field: {$requiredField}");
        }
    }

    if (! is_array($workflow->nodes) || count($workflow->nodes) === 0) {
        failImport('Workflow JSON must include at least one node.');
    }

    $payload = new stdClass();
    $payload->name = $workflow->name;
    $payload->nodes = $workflow->nodes;
    $payload->connections = $workflow->connections;
    $payload->settings = property_exists($workflow, 'settings') ? $workflow->settings : new stdClass();
    $payload->active = false;

    foreach (['staticData', 'pinData', 'tags'] as $optionalField) {
        if (property_exists($workflow, $optionalField)) {
            $payload->{$optionalField} = $workflow->{$optionalField};
        }
    }

    return $payload;
}

function postWorkflowToN8n(string $endpoint, string $apiKey, stdClass $payload): array
{
    if (! function_exists('curl_init')) {
        failImport('The PHP cURL extension is required to import the workflow.');
    }

    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $handle = curl_init($endpoint);

    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-N8N-API-KEY: '.$apiKey,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $responseBody = curl_exec($handle);
    $curlError = curl_error($handle);
    $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);

    curl_close($handle);

    if ($responseBody === false) {
        failImport("n8n import request failed: {$curlError}");
    }

    return [$statusCode, (string) $responseBody];
}

if (! is_file($workflowPath)) {
    failImport("Workflow artifact not found: {$workflowPath}");
}

$workflowJson = file_get_contents($workflowPath);

if ($workflowJson === false) {
    failImport("Could not read workflow artifact: {$workflowPath}");
}

$secretIssues = scanWorkflowForSecrets($workflowJson);

if ($secretIssues !== []) {
    failImport("Workflow JSON failed secret safety checks:\n - ".implode("\n - ", $secretIssues));
}

try {
    $workflow = json_decode($workflowJson, false, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    failImport('Workflow JSON is invalid: '.$exception->getMessage());
}

if (! $workflow instanceof stdClass) {
    failImport('Workflow JSON must decode to an object.');
}

if (! property_exists($workflow, 'active')) {
    failImport('Workflow JSON must explicitly include "active": false.');
}

if ($workflow->active !== false) {
    failImport('Workflow import refused because the workflow is active. Set "active": false first.');
}

$payload = buildN8nCreatePayload($workflow);
successLine('Workflow artifact is valid and disabled.');
successLine('Workflow secret safety checks passed.');

if ($dryRun) {
    infoLine('Dry run complete. No request was sent to n8n.');
    exit(0);
}

$n8nUrl = normalizeN8nUrl(requiredEnvValue('N8N_URL'));
$apiKey = requiredEnvValue('N8N_API_KEY');
$endpoint = $n8nUrl.'/api/v1/workflows';

infoLine("Importing disabled workflow to {$endpoint}");

[$statusCode, $responseBody] = postWorkflowToN8n($endpoint, $apiKey, $payload);

if ($statusCode < 200 || $statusCode >= 300) {
    failImport("n8n import failed with HTTP {$statusCode}: ".jsonPreview($responseBody));
}

try {
    $response = json_decode($responseBody, false, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    failImport('n8n returned non-JSON success response, so disabled status could not be confirmed.');
}

$createdWorkflow = $response instanceof stdClass
    && property_exists($response, 'data')
    && $response->data instanceof stdClass
        ? $response->data
        : $response;

if (! $createdWorkflow instanceof stdClass || ! property_exists($createdWorkflow, 'active')) {
    failImport('n8n response did not include workflow active status; import result cannot be trusted.');
}

if ($createdWorkflow->active !== false) {
    failImport('n8n imported workflow but did not keep it disabled. Review n8n immediately.');
}

$workflowId = property_exists($createdWorkflow, 'id') ? (string) $createdWorkflow->id : 'unknown';
$workflowName = property_exists($createdWorkflow, 'name') ? (string) $createdWorkflow->name : 'BlendNews RSS Draft Intake';

successLine("Imported disabled n8n workflow: {$workflowName} (id: {$workflowId})");
