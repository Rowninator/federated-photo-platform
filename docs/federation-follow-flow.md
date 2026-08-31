# Federation Follow flow

This document records the Follow flow investigated in Pixelfed and contrasts it
with the repository's current experiment. It describes present observations and
prototype behavior, not an architecture decision for the future application.

## Observed Pixelfed behavior

- A remote `Follow` arrives through an ActivityPub inbox route.
- Signature and identity validation happen before normal state-changing
  handling of the incoming request.
- Activity IDs are deduplicated before processing.
- A public local profile can create the follower relationship immediately.
- A private local profile creates a pending follow request.
- Accepting a Follow produces an ActivityPub `Accept` whose `object` references
  the original `Follow`.
- Outgoing ActivityPub delivery is signed and sent to the remote inbox.

## Our current prototype

The [Python inbox experiment](../experiments/activitypub_inbox.py) currently
provides:

- an in-memory activity dispatcher;
- minimal URL and hostname-based identity validation;
- in-memory activity-ID deduplication;
- public and private profile Follow behavior;
- pending follow-request acceptance and rejection;
- ActivityPub `Accept` and `Reject` dictionary builders;
- a mock in-memory outbound queue that preserves activities and destinations;
- a [runnable Follow-flow demo](../experiments/demo_follow_flow.py); and
- an [interactive HTML visualization](../experiments/federation_flow_visual.html).

The prototype does **not** implement:

- real HTTP inbox endpoints;
- HTTP signatures;
- actor discovery;
- persistence or database storage;
- real queues; or
- real network delivery.

The prototype is a learning aid. Its use of Python, ActivityPub-shaped data,
and in-memory structures does not select the architecture or technology stack
for the future application.
