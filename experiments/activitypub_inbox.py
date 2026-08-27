"""Small ActivityPub inbox-dispatch experiment."""


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

    activity_type = activity.get("type")

    if activity_type == "Follow":
        handle_follow(activity)
    elif activity_type == "Like":
        handle_like(activity)
    elif activity_type == "Create":
        handle_create(activity)
    else:
        handle_unsupported(activity)


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
