# BlendNews Automation Manual API Tests

Use these commands after local/staging environment variables are configured.

Placeholders:

- `<APP_URL>`: base app URL, for example local or staging.
- `<AUTOMATION_API_TOKEN>`: automation bearer token from the target environment.

Do not paste a real token into committed files.

## Valid Rules Request

```bash
curl -sS -X GET "<APP_URL>/api/automation/news/rules" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <AUTOMATION_API_TOKEN>"
```

Expected response:

```json
{
  "data": [
    {
      "id": 1,
      "name": "BlendNews RSS Draft Intake",
      "slug": "blendnews-rss-draft-intake",
      "rule_type": "rss_intake",
      "event_type": null,
      "milestone_key": null,
      "source_type": "rss",
      "source_id": null,
      "cooldown_minutes": 30,
      "priority": 1,
      "metadata": {
        "draft_status": "review",
        "workflow_name": "BlendNews RSS Draft Intake"
      }
    }
  ]
}
```

If migrations or seeders have not been applied yet, `data` may be an empty array.

## Log Creation

```bash
curl -sS -X POST "<APP_URL>/api/automation/news/logs" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <AUTOMATION_API_TOKEN>" \
  -d '{
    "workflow_name": "BlendNews RSS Draft Intake",
    "status": "started",
    "message": "Manual automation log test started.",
    "payload": {
      "manual_test": true
    }
  }'
```

Expected response:

```json
{
  "data": {
    "id": 1,
    "workflow_name": "BlendNews RSS Draft Intake",
    "rule_id": null,
    "event_id": null,
    "status": "started",
    "message": "Manual automation log test started.",
    "payload": {
      "manual_test": true
    },
    "error_message": null,
    "started_at": null,
    "finished_at": null,
    "created_at": "2026-06-19T00:00:00.000000Z"
  }
}
```

## RSS Draft Creation

```bash
curl -sS -X POST "<APP_URL>/api/automation/news/rss-drafts" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <AUTOMATION_API_TOKEN>" \
  -d '{
    "source_name": "Manual Test Source",
    "source_url": "https://example.com/manual-test-story",
    "source_feed_url": "https://example.com/rss",
    "source_guid": "manual-test-guid-001",
    "original_title": "Original manual test headline",
    "title": "Manual BlendNews Draft",
    "summary": "Manual test summary for a BlendNews automation draft.",
    "status": "needs_review",
    "content_type": "news",
    "category_slug": null,
    "metadata": {
      "created_by_automation": true,
      "workflow_name": "BlendNews RSS Draft Intake"
    }
  }'
```

Expected response:

```json
{
  "data": {
    "id": 1,
    "title": "Manual BlendNews Draft",
    "slug": "manual-blendnews-draft",
    "status": "review",
    "content_type": "news",
    "author_id": null,
    "category_id": null,
    "news_source_id": 1,
    "excerpt": "Manual test summary for a BlendNews automation draft.",
    "metadata": {
      "created_by_automation": true,
      "workflow_name": "BlendNews RSS Draft Intake",
      "source_name": "Manual Test Source",
      "source_url": "https://example.com/manual-test-story",
      "source_feed_url": "https://example.com/rss",
      "source_guid": "manual-test-guid-001",
      "original_title": "Original manual test headline"
    },
    "created_at": "2026-06-19T00:00:00.000000Z"
  },
  "automation_log": {
    "id": 2,
    "status": "success"
  }
}
```

## Duplicate RSS Skip

Run the same RSS draft creation command again with the same `source_guid` or `source_url`.

Expected response:

```json
{
  "skipped": true,
  "reason": "duplicate_source"
}
```

## Invalid Token 401

```bash
curl -sS -X GET "<APP_URL>/api/automation/news/rules" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer invalid-<AUTOMATION_API_TOKEN>"
```

Expected response:

```json
{
  "message": "Invalid automation token."
}
```

Expected HTTP status: `401`.

## Automation Disabled 403

Use the valid token while `AUTOMATION_NEWS_ENABLED=false` in the target environment.

```bash
curl -sS -X GET "<APP_URL>/api/automation/news/rules" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <AUTOMATION_API_TOKEN>"
```

Expected response:

```json
{
  "message": "Automation is disabled."
}
```

Expected HTTP status: `403`.
