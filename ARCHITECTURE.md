# Architecture

## Purpose

This document records approved, foundation-level architecture decisions. The
project maintainers should update it when those decisions change.

## Current foundation

- The real backend application lives in `server/`.
- The backend framework is Laravel 12.x.
- The supported course PHP environment is PHP 8.3 or 8.4.
- The production-target relational database is MySQL.
- SQLite may be used temporarily for foundation and test convenience.

## Local identity and authentication

- `User` represents private authentication and account identity; `Profile`
  represents public social identity.
- Each local `User` has one `Profile`.
- Local usernames are normalized to lowercase and are unique.
- Local web authentication uses Laravel session authentication.
- Token and mobile authentication and username changes remain separate and
  intentionally deferred.

## Local media foundation

- `Media` belongs to `Profile`; uploads exist independently until attached to a
  `Status`, and deletion is restricted to the owning user.
- Image bytes live in filesystem/object storage, not the relational database.
  The database stores ownership and media metadata.
- Application code addresses media by Laravel filesystem disk + server-generated
  relative path, not machine-specific paths or client filenames.
- Development uses a private local `media` disk.
- Uploaded originals remain private. Display and thumbnail variants are derived
  from the retained original and can be rebuilt from it.
- Initial supported upload formats are JPEG and PNG.
- Upload authentication and validation, trusted source inspection, private
  original persistence, and creation of a pending `Media` row happen
  synchronously. Display and thumbnail variants are generated asynchronously.
- Media processing states are `pending`, `processing`, `ready`, and `failed`.
  Processing is dispatched after the database transaction commits and runs on
  the `media` queue. Only ready Media may be attached to a Status.
- Terminal processing failure retains the private original for retry or
  recovery.
- Owners may inspect minimal processing state through `GET /media/{media}`;
  internal storage paths and `processing_error` are not exposed.
- Production storage can later move behind the same Laravel filesystem
  abstraction without rewriting domain behavior.

## Local statuses and interactions

- `Status` is the core local content entity shared by ordinary posts, replies,
  and reposts.
- A top-level photo Status contains one to four ordered `Media` records. Post
  and reply captions are limited to 500 characters; reposts have no caption.
- A repost references the canonical original Status rather than copying its
  content or Media.
- Likes and Bookmarks are `Profile`-to-`Status` relationships. Database
  uniqueness prevents duplicate Likes, Bookmarks, and reposts for the same
  Profile and target; Bookmarks remain private user state.
- Module 6 comments are text-only and restricted to top-level posts. Nested
  replies and Media attachments are unsupported, and only the author may
  delete a comment through the comment endpoint.
- Status visibility remains intentionally deferred.

## Local following and relationships

- `Follow` is a directed `Profile`-to-`Profile` relationship. A public target
  establishes it immediately; a private target creates a pending
  `FollowRequest`.
- Accepting a FollowRequest atomically establishes the Follow and removes the
  request. Database uniqueness prevents duplicate Follows and FollowRequests,
  and self-follow is invalid.
- Unfollow, pending-request cancellation, and follower removal operate only on
  the specified direction of the relationship.
- `Profile.is_private` currently means only "new followers require approval."
  It does not hide Status content from non-followers, and changing it does not
  change existing Status visibility.
- Blocking, muting, notifications, and federation delivery remain deferred.

## Local timelines

- Home and public timeline Status queries remain database-backed; complete
  timelines and timeline JSON are not cached.
- Home timeline membership consists of the authenticated Profile and Profiles
  reached through established Follow rows; pending FollowRequests do not grant
  membership.
- Timelines contain only ordinary top-level Status records. Replies and repost
  Status rows are deliberately excluded from this initial baseline. The public
  timeline includes qualifying posts from every local Profile.
- `Profile.is_private` controls follow approval only; timelines do not yet
  enforce follower-only Status visibility.
- Timeline ordering is `created_at DESC, id DESC`. Reads use cursor/keyset
  pagination rather than offset/page-number pagination, with a default page
  size of 20 and an accepted range of 1–40.
- Timeline reads eager-load Profile and position-ordered Media, and load Like,
  reply, and repost counts through aggregate counts rather than per-item
  relationship queries.
- The relational baseline includes a supporting Status timeline index.

## Derived Redis state and asynchronous work

- Redis cache is derived, rebuildable state. It currently caches established
  following Profile IDs, while the relational `Follow` table remains
  canonical. Established Follow changes invalidate the relevant cache; cache
  loss rebuilds membership from the database rather than losing social data.
- Redis separately stores pending asynchronous jobs. Queue state is not
  canonical application content, and cache and queue remain distinct Redis
  responsibilities.
- Horizon supervises and observes Redis queue workers in the supported
  Linux/WSL environment; native Windows PHP is not the Horizon runtime. The
  local supervisor processes the `media` and `default` queues.
- Normal automated tests use the array cache and synchronous or fake queues as
  appropriate, so they do not require live Redis. Real Redis and Horizon
  integration are verified separately.

## Federation identity and WebFinger

- A local federation address is `acct:{username}@{domain}`. The canonical
  domain and base URL come from configuration, never the incoming request
  `Host`; the stable future actor URI is `{base-url}/users/{username}`.
- The public `/.well-known/webfinger` endpoint returns JRD whose ActivityPub
  `self` link identifies that future actor URI. Email and private account data
  are not exposed.
- Remote handles normalize to `acct:` URIs. The actor path is never guessed:
  remote WebFinger must return exactly one supported `rel=self` ActivityPub or
  ActivityStreams link. No remote Profile is persisted, and Actor document
  retrieval is deferred to Module 11 with Actor representation.
- Remote HTTP discovery is SSRF-sensitive. The current baseline permits only
  validated HTTPS federation destinations; it rejects localhost, local-only
  hosts, IP literals, insecure schemes, and URL credentials, and disables
  redirects. Comprehensive federation and network hardening is deferred to
  Module 14.

## Intentionally deferred

Broader web, mobile, and federation systems are intentionally deferred.
