# BlendNews RSS Draft Intake n8n Workflow Plan

Workflow name: `BlendNews RSS Draft Intake`

This is a design document only. Do not create the n8n workflow from this plan until the review checklist is approved.

## Placeholders

- `<APP_URL>`: Laravel app base URL.
- `<AUTOMATION_API_TOKEN>`: Laravel automation bearer token.
- `<RSS_FEED_URL>`: RSS feed URL configured for the first beta source.

## Flow

1. Schedule Trigger
2. RSS Read
3. Split Items
4. Normalize item
5. POST to Laravel `/api/automation/news/rss-drafts`
6. POST to Laravel `/api/automation/news/logs` if needed
7. POST to Laravel `/api/automation/news/notifications` if needed

## Node List

### 1. Schedule Trigger

Purpose: Starts the workflow on a fixed beta schedule.

Recommended beta schedule:

- Every 30 minutes during the beta period.
- Disable overlapping executions.
- Run during normal monitoring hours first, then expand after duplicate handling and admin review are confirmed.

### 2. RSS Read

Purpose: Reads RSS items from the configured feed.

Required value:

- Feed URL: `<RSS_FEED_URL>`

Output expectation:

- Each item should include a title, link, guid or id, date, and content or summary when available.

### 3. Split Items

Purpose: Processes one RSS item at a time so each article gets its own Laravel request and log path.

Recommended settings:

- Batch size: `1`
- Continue on fail: off for the node, with failure routed to the workflow error path if available.

### 4. Normalize Item

Purpose: Converts the RSS item into the Laravel `/api/automation/news/rss-drafts` payload.

Normalization rules:

- Prefer RSS `guid` as `source_guid`.
- If RSS `guid` is missing, use the item link as `source_guid`.
- Use RSS link as `source_url`.
- Use the feed URL as `source_feed_url`.
- Use the RSS title for `original_title`.
- Create a concise draft `title` from the RSS title.
- Create `summary` from RSS summary/content snippet. Keep it short and review-friendly.
- Send `status` as `needs_review`; Laravel stores it internally as `review`.
- Do not send `published`, `approved`, or `archived`.

Sample normalized payload:

```json
{
  "source_name": "RSS Feed",
  "source_url": "{{$json.link}}",
  "source_feed_url": "<RSS_FEED_URL>",
  "source_guid": "{{$json.guid || $json.link}}",
  "original_title": "{{$json.title}}",
  "title": "{{$json.title}}",
  "summary": "{{$json.contentSnippet || $json.content || $json.description}}",
  "status": "needs_review",
  "content_type": "news",
  "category_slug": null,
  "metadata": {
    "created_by_automation": true,
    "workflow_name": "BlendNews RSS Draft Intake"
  }
}
```

### 5. POST RSS Draft To Laravel

Purpose: Sends the normalized item to Laravel so Laravel can decide whether to create or skip the draft.

Request:

- Method: `POST`
- URL: `<APP_URL>/api/automation/news/rss-drafts`
- Headers:
  - `Accept: application/json`
  - `Content-Type: application/json`
  - `Authorization: Bearer <AUTOMATION_API_TOKEN>`
- Body: normalized payload from node 4.

Expected outcomes:

- `201`: Draft was created.
- `200` with `skipped: true`: Laravel skipped the item, usually because it was a duplicate.
- `401`: Automation token is missing or invalid.
- `403`: Automation news API is disabled.
- `422`: Payload is invalid or status is not allowed.
- `503`: Required Laravel automation table is not available.

### 6. POST Log To Laravel If Needed

Purpose: Records workflow-level events that are not already logged by Laravel endpoint behavior.

Laravel already logs:

- RSS draft created.
- RSS duplicate skipped.
- RSS draft rejected by controlled validation paths.

Use `/api/automation/news/logs` for extra workflow-level events such as:

- Workflow started.
- RSS read returned zero items.
- n8n normalization failed before calling Laravel.
- n8n HTTP request failed before Laravel returned a normal JSON response.

Request:

- Method: `POST`
- URL: `<APP_URL>/api/automation/news/logs`
- Headers:
  - `Accept: application/json`
  - `Content-Type: application/json`
  - `Authorization: Bearer <AUTOMATION_API_TOKEN>`

Sample body:

```json
{
  "workflow_name": "BlendNews RSS Draft Intake",
  "status": "started",
  "message": "RSS intake workflow started.",
  "payload": {
    "feed_url": "<RSS_FEED_URL>"
  }
}
```

### 7. POST Notification To Laravel If Needed

Purpose: Sends admin-facing database notifications for summary or error conditions.

Use notifications for:

- Workflow completed with created drafts.
- Workflow completed with only skipped duplicates.
- RSS feed failed or returned malformed items.
- Laravel returned repeated non-success responses.

Request:

- Method: `POST`
- URL: `<APP_URL>/api/automation/news/notifications`
- Headers:
  - `Accept: application/json`
  - `Content-Type: application/json`
  - `Authorization: Bearer <AUTOMATION_API_TOKEN>`

Sample body:

```json
{
  "workflow_name": "BlendNews RSS Draft Intake",
  "status": "success",
  "message": "RSS intake completed.",
  "payload": {
    "feed_url": "<RSS_FEED_URL>",
    "created_count": 1,
    "skipped_count": 0,
    "failed_count": 0
  }
}
```

## Duplicate Behavior

Laravel is the source of truth for duplicates.

The RSS draft endpoint checks:

- `metadata->source_guid`
- `metadata->source_url`

When Laravel returns:

```json
{
  "skipped": true,
  "reason": "duplicate_source"
}
```

n8n should:

- Treat the item as successfully handled.
- Increment a skipped counter.
- Avoid retrying the same item in the same run.
- Avoid sending an admin alert for a normal duplicate unless duplicate volume becomes unusual.

## Failure Behavior

Authentication failures:

- `401`: Stop the workflow and notify admins if the notification endpoint is reachable with a valid token later.
- `403`: Stop the workflow. Automation is intentionally disabled.

Validation failures:

- `422`: Log the item payload shape and stop or quarantine the item for review.
- Do not retry unchanged invalid payloads.

Service readiness failures:

- `503`: Stop the workflow. Laravel tables or automation dependencies are not ready.

RSS feed failures:

- Create a log with status `failed`.
- Send a notification if the notifications endpoint is reachable.
- Do not create drafts from partial or malformed RSS items.

Network failures:

- Retry with n8n's conservative retry settings.
- If retries fail, create a failure log on the next successful Laravel connection.

## Required Credentials And Configuration

Laravel HTTP credential:

- Base URL: `<APP_URL>`
- Authorization header: `Bearer <AUTOMATION_API_TOKEN>`
- Use this credential for all Laravel HTTP Request nodes.

RSS source:

- Feed URL: `<RSS_FEED_URL>`

Environment assumptions:

- `AUTOMATION_NEWS_ENABLED=true` in the target Laravel environment.
- `AUTOMATION_API_TOKEN` is set in the target Laravel environment.
- Automation migrations have been applied.
- The seed rule has been applied or a matching active rule exists.

## Beta Schedule Recommendation

Start with:

- Every 30 minutes.
- One RSS feed only.
- Admin review required for every draft.
- Daily review of automation logs.

After beta confidence:

- Add more feeds one at a time.
- Keep drafts in `review`.
- Consider reducing schedule interval only after duplicate and failure rates are stable.

## Review Checklist Before Creating Workflow In n8n

- Confirm `<APP_URL>` points to the intended Laravel environment.
- Confirm `<AUTOMATION_API_TOKEN>` is stored only in n8n credentials.
- Confirm `<RSS_FEED_URL>` is approved for beta intake.
- Confirm automation API returns rules with the active RSS intake rule.
- Confirm `/api/automation/news/logs` can create a test log.
- Confirm `/api/automation/news/rss-drafts` creates a review draft with a test RSS item.
- Confirm a second send of the same item returns `duplicate_source`.
- Confirm `/api/automation/news/notifications` creates a log and sends or safely skips notifications.
- Confirm admins know drafts remain in review and are not published by automation.
- Confirm rollback plan: disable `AUTOMATION_NEWS_ENABLED` or disable the n8n schedule.
