import unittest

from experiments.activitypub_inbox import validate_activity


class ValidateActivityTests(unittest.TestCase):
    def test_valid_follow_passes_validation(self) -> None:
        activity = {
            "id": "follow-1",
            "type": "Follow",
            "actor": "actor-1",
            "object": "actor-2",
        }

        self.assertTrue(validate_activity(activity))

    def test_follow_without_object_fails_validation(self) -> None:
        activity = {
            "id": "follow-1",
            "type": "Follow",
            "actor": "actor-1",
        }

        self.assertFalse(validate_activity(activity))

    def test_activity_without_required_field_fails_validation(self) -> None:
        activity = {
            "id": "like-1",
            "type": "Like",
        }

        self.assertFalse(validate_activity(activity))


if __name__ == "__main__":
    unittest.main()
