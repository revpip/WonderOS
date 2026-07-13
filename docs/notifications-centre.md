# WonderOS Notifications Centre

Genesis-024 adds a personal in-app inbox for accountable editorial work.

## Events

- `assignment`: created when work is assigned to a user.
- `mention`: created for each explicitly mentioned user in claim discussion.
- `due_soon`: generated when an open assignment is due within 48 hours.
- `overdue`: generated when an open assignment is past its due date.

Due notifications use stable deduplication keys, so repeated generation does not create duplicates.

## API

- `GET /v1/notifications?unread_only=true&limit=100`
- `POST /v1/notifications/{uuid}/read`
- `POST /v1/notifications/read-all`

All routes require an authenticated WonderOS session. Users can access only their own notifications.

## Console

Open `http://localhost:8081/notifications.html` after signing in.

## Operational boundary

Due-date generation currently runs when the inbox is requested. Production should move this to an hourly scheduler or worker. Email, push delivery, notification preferences, quiet hours and digesting remain future work.
