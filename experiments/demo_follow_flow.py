"""Demonstrate an in-memory private-profile Follow flow."""

from activitypub_inbox import (
    dispatch_activity,
    local_profiles,
    outbound_activities,
    process_accept_follow_request,
    processed_activity_ids,
)


def main() -> None:
    alice = "https://remote.example/users/alice"
    alice_inbox = "https://remote.example/users/alice/inbox"
    bob = "https://social.example/users/bob"

    local_profiles.clear()
    processed_activity_ids.clear()
    outbound_activities.clear()

    local_profiles[bob] = {
        "is_private": True,
        "followers": set(),
        "pending_follow_requests": set(),
    }
    follow_activity = {
        "id": "https://remote.example/activities/follow-1",
        "type": "Follow",
        "actor": alice,
        "object": bob,
    }

    print("1. Bob is a private local profile.")
    print("2. Alice sends Bob a Follow activity.")
    dispatch_activity(follow_activity)
    print("3. Pending requests:", sorted(local_profiles[bob]["pending_follow_requests"]))

    print("4. Bob accepts Alice's follow request.")
    accept_activity = process_accept_follow_request(
        bob,
        follow_activity,
        "https://social.example/activities/accept-1",
        alice_inbox,
    )
    print("   Followers:", sorted(local_profiles[bob]["followers"]))
    print("   Pending requests:", sorted(local_profiles[bob]["pending_follow_requests"]))
    print("5. Accept activity created:", accept_activity)
    print("6. Outbound delivery queued:", outbound_activities[0])


if __name__ == "__main__":
    main()
