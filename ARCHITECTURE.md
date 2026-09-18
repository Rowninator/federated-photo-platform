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
- Token and mobile authentication, username changes, and federation identity
  remain separate and intentionally deferred.

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
- Image processing is synchronous for now; asynchronous processing is deferred
  to the future queue module.
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
- Blocking, muting, and notifications are deliberately deferred. Federation
  remains a separate, paused workstream.

## Local timelines

- Home and public timelines are currently database-backed. Redis and feed
  caching are deliberately deferred to Module 9.
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

## Intentionally deferred

Broader web, mobile, and federation systems are intentionally deferred.
