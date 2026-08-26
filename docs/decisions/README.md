# Decision records

Use a decision record for a consequential product or technical choice that
future contributors will need to understand. A record explains the context and
tradeoffs; it is not a place to make an unapproved proposal look settled.

## Current decisions

No decisions have been recorded yet.

## Process

1. Discuss or investigate the question in the appropriate working context.
2. Once a choice is approved, copy [`000-template.md`](000-template.md) to the
   next available number, followed by a short kebab-case title (for example,
   `001-example-title.md`).
3. Set its status and date, state the decision precisely, and link evidence or
   related work where useful.
4. Add it to the list in this file.
5. Do not rewrite the history of an accepted record. Add a new record that
   supersedes it and cross-link both records.

Allowed statuses are `Accepted`, `Superseded`, and `Deprecated`. Decision
records are created only after approval and are not used to track proposals.
Only `Accepted` records represent current decisions.
