"""Small ActivityPub inbox-dispatch experiment."""

from urllib.parse import urlparse


processed_activity_ids = set()


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
    print("Follow activity:", activity)


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
    follow_activity = {
        "id": "https://social.example/activities/follow-1",
        "type": "Follow",
        "actor": "https://social.example/users/alice",
        "object": "https://remote.example/users/bob",
    }
    invalid_follow_activity = {
        "id": "https://social.example/activities/follow-2",
        "type": "Follow",
        "actor": "https://social.example/users/alice",
    }

    dispatch_activity(follow_activity)
    dispatch_activity(invalid_follow_activity)
