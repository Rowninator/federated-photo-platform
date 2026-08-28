"""Small ActivityPub inbox-dispatch experiment."""

from urllib.parse import urlparse


processed_activity_ids = set()
local_profiles = {}


def extract_hostname(value: object):
    if not isinstance(value, str):
        return None

    try:
        parsed_url = urlparse(value)
        return parsed_url.hostname if parsed_url.scheme else None
    except ValueError:
        return None


def validate_activity(activity: dict) -> bool:
    required_fields = {"id", "type", "actor"}

    if not required_fields.issubset(activity):
        return False

    if activity["type"] != "Follow":
        return True

    if "object" not in activity:
        return False

    id_hostname = extract_hostname(activity["id"])
    actor_hostname = extract_hostname(activity["actor"])
    object_hostname = extract_hostname(activity["object"])

    return bool(
        id_hostname
        and actor_hostname
        and object_hostname
        and id_hostname == actor_hostname
    )


def handle_follow(activity: dict) -> None:
    target_profile = local_profiles.get(activity["object"])

    if target_profile is None:
        print("Rejected Follow activity:", activity)
        return

    if target_profile["is_private"]:
        target_profile["pending_follow_requests"].add(activity["actor"])
    else:
        target_profile["followers"].add(activity["actor"])

    print("Follow activity:", activity)


def accept_follow_request(profile_url: str, actor: str) -> None:
    profile = local_profiles.get(profile_url)

    if profile is None or actor not in profile["pending_follow_requests"]:
        return

    profile["pending_follow_requests"].remove(actor)
    profile["followers"].add(actor)


def reject_follow_request(profile_url: str, actor: str) -> None:
    profile = local_profiles.get(profile_url)

    if profile is None or actor not in profile["pending_follow_requests"]:
        return

    profile["pending_follow_requests"].remove(actor)


def build_accept_activity(
    activity_id: str, actor: str, follow_activity: dict
) -> dict:
    return {
        "id": activity_id,
        "type": "Accept",
        "actor": actor,
        "object": follow_activity,
    }


def build_reject_activity(
    activity_id: str, actor: str, follow_activity: dict
) -> dict:
    return {
        "id": activity_id,
        "type": "Reject",
        "actor": actor,
        "object": follow_activity,
    }


def handle_like(activity: dict) -> None:
    print("Like activity:", activity)


def handle_create(activity: dict) -> None:
    print("Create activity:", activity)


def handle_unsupported(activity: dict) -> None:
    print("Unsupported activity:", activity)


def dispatch_activity(activity: dict) -> None:
    if not validate_activity(activity):
        print("Invalid activity")
        return

    activity_id = activity["id"]
    if activity_id in processed_activity_ids:
        print("Duplicate activity")
        return

    activity_type = activity.get("type")

    if activity_type == "Follow":
        handler = handle_follow
    elif activity_type == "Like":
        handler = handle_like
    elif activity_type == "Create":
        handler = handle_create
    else:
        handler = handle_unsupported

    handler(activity)
    processed_activity_ids.add(activity_id)


if __name__ == "__main__":
    local_profile_url = "https://social.example/users/bob"
    local_profiles[local_profile_url] = {
        "is_private": False,
        "followers": set(),
        "pending_follow_requests": set(),
    }

    follow_activity = {
        "id": "https://remote.example/activities/follow-1",
        "type": "Follow",
        "actor": "https://remote.example/users/alice",
        "object": local_profile_url,
    }
    invalid_follow_activity = {
        "id": "https://remote.example/activities/follow-2",
        "type": "Follow",
        "actor": "https://remote.example/users/alice",
    }

    dispatch_activity(follow_activity)
    dispatch_activity(invalid_follow_activity)
