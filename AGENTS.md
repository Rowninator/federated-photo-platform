# Repository guidance for coding agents

## Mission

Help this repository grow into a cross-platform, federated photo-sharing
application while preserving a reliable record of what the project actually
knows. At this stage, improve the environment for future development; do not
prematurely implement the application.

## Source of truth

Read these files before making changes:

1. `README.md` for project status and navigation.
2. `docs/product/overview.md` for confirmed product context and open questions.
3. `docs/decisions/` for accepted decisions, when any exist.
4. The relevant plan in `docs/plans/`, when work has an active plan.

More specific `AGENTS.md` files may be added later and take precedence within
their directories.

## Working rules

- Treat an unanswered question as unknown, not as permission to choose an
  answer silently.
- Do not infer a programming language, framework, database, federation
  protocol, hosting model, repository layout, or client architecture.
- Separate confirmed facts, proposals, and decisions in documentation.
- Record a consequential, durable choice as a decision record when the choice
  is approved; do not create decision records merely to speculate.
- Keep changes small and within the requested scope. Do not scaffold
  application code unless the task explicitly calls for it and the required
  decisions have been made.
- Update navigation links when adding, moving, or removing documentation.
- Prefer links to the canonical document over duplicating its contents.
- Never include secrets, credentials, private user data, or production data in
  the repository.

## Validation

There is currently no build command. Run the ActivityPub experiment tests from
the repository root with:

```text
py -3 -m unittest discover -s tests
```

Run the private-profile Follow flow demo with:

```text
py -3 experiments\demo_follow_flow.py
```

Run the WebFinger discovery demo with:

```text
py -3 experiments\webfinger_discovery.py
```

For documentation-only changes:

- inspect the diff for unsupported claims;
- verify relative links and file names;
- ensure every new document has a clear purpose and owner or update trigger.

If more executable tooling is introduced later, document its setup and
validation commands here in the same change.
