import unittest
from contextlib import redirect_stdout
from io import StringIO

from experiments.activitypub_inbox import (
    dispatch_activity,
    local_profiles,
    processed_activity_ids,
    validate_activity,
)


class ValidateActivityTests(unittest.TestCase):
    def test_follow_with_matching_identity_hostnames_passes_validation(self) -> None:
        activity = {
            "id": "https://social.example/activities/follow-1",
            "type": "Follow",
            "actor": "https://social.example/users/alice",
            "object": "https://remote.example/users/bob",
        }

        self.assertTrue(validate_activity(activity))

    def test_follow_with_different_identity_hostnames_fails_validation(self) -> None:
        activity = {
            "id": "https://social.example/activities/follow-1",
            "type": "Follow",
            "actor": "https://other.example/users/alice",
            "object": "https://remote.example/users/bob",
        }

        self.assertFalse(validate_activity(activity))

    def test_follow_without_object_fails_validation(self) -> None:
        activity = {
            "id": "https://social.example/activities/follow-1",
            "type": "Follow",
            "actor": "https://social.example/users/alice",
        }

        self.assertFalse(validate_activity(activity))

    def test_activity_without_required_field_fails_validation(self) -> None:
        activity = {
            "id": "like-1",
            "type": "Like",
        }

        self.assertFalse(validate_activity(activity))


class DispatchActivityTests(unittest.TestCase):
    def setUp(self) -> None:
        processed_activity_ids.clear()
        local_profiles.clear()

        self.actor_url = "https://remote.example/users/alice"
        self.target_url = "https://social.example/users/bob"
        self.activity = {
            "id": "https://remote.example/activities/follow-1",
            "type": "Follow",
            "actor": self.actor_url,
            "object": self.target_url,
        }
        local_profiles[self.target_url] = {
            "is_private": False,
            "followers": set(),
            "pending_follow_requests": set(),
        }

    def test_new_valid_activity_is_processed(self) -> None:
        output = StringIO()

        with redirect_stdout(output):
            dispatch_activity(self.activity)

        self.assertIn("Follow activity:", output.getvalue())
        self.assertIn(self.activity["id"], processed_activity_ids)

    def test_second_attempt_is_rejected_as_duplicate(self) -> None:
        output = StringIO()

        with redirect_stdout(output):
            dispatch_activity(self.activity)
            dispatch_activity(self.activity)

        lines = output.getvalue().splitlines()
        self.assertEqual(2, len(lines))
        self.assertTrue(lines[0].startswith("Follow activity:"))
        self.assertEqual("Duplicate activity", lines[1])

    def test_public_profile_accepts_follow(self) -> None:
        with redirect_stdout(StringIO()):
            dispatch_activity(self.activity)

        profile = local_profiles[self.target_url]
        self.assertIn(self.actor_url, profile["followers"])
        self.assertEqual(set(), profile["pending_follow_requests"])

    def test_private_profile_creates_pending_follow_request(self) -> None:
        local_profiles[self.target_url]["is_private"] = True

        with redirect_stdout(StringIO()):
            dispatch_activity(self.activity)

        profile = local_profiles[self.target_url]
        self.assertEqual(set(), profile["followers"])
        self.assertIn(self.actor_url, profile["pending_follow_requests"])

    def test_unknown_profile_does_not_change_relationship_state(self) -> None:
        unknown_target_activity = self.activity | {
            "object": "https://social.example/users/unknown"
        }

        with redirect_stdout(StringIO()):
            dispatch_activity(unknown_target_activity)

        profile = local_profiles[self.target_url]
        self.assertEqual(set(), profile["followers"])
        self.assertEqual(set(), profile["pending_follow_requests"])
        self.assertNotIn(unknown_target_activity["object"], local_profiles)


if __name__ == "__main__":
    unittest.main()
