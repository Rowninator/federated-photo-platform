"""Small ActivityPub inbox-dispatch experiment."""


processed_activity_ids = set()


def validate_activity(activity: dict) -> bool:
    required_fields = {"id", "type", "actor"}

    if not required_fields.issubset(activity):
        return False

    return activity["type"] != "Follow" or "object" in activity


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
        "id": "follow-1",
        "type": "Follow",
        "actor": "actor-1",
        "object": "actor-2",
    }
    invalid_follow_activity = {
        "id": "follow-2",
        "type": "Follow",
        "actor": "actor-1",
    }

    dispatch_activity(follow_activity)
    dispatch_activity(invalid_follow_activity)
