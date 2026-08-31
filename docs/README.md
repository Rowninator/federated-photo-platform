# Documentation

This directory is the canonical home for project knowledge that should outlive
a chat, issue, or coding session. Keep documents short, link related material,
and update them when their underlying facts change.

## Map

| Area | Purpose | Update when |
| --- | --- | --- |
| [Product](product/overview.md) | Confirmed product intent, boundaries, and open questions | Product understanding changes |
| [Federation Follow flow](federation-follow-flow.md) | Observed Pixelfed behavior, current prototype scope, and known gaps | Follow-flow findings or experiments change |
| [Decisions](decisions/README.md) | Accepted or superseded consequential choices | A durable choice is approved or revisited |
| [Plans](plans/README.md) | Time-bounded plans for concrete work | A substantial effort starts, changes, or finishes |

## Documentation conventions

- Label proposals as proposals. They are not decisions.
- Put durable choices in `decisions/`, not in plans.
- Put temporary sequencing and execution notes in `plans/`, not in the product
  overview.
- Prefer dates in `YYYY-MM-DD` format.
- Use repository-relative links so documents work locally and on a Git host.

Add a new documentation category only after multiple documents need it. This
keeps the structure proportional to the project.
