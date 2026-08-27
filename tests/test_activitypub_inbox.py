import unittest
from contextlib import redirect_stdout
from io import StringIO

from experiments.activitypub_inbox import (
    dispatch_activity,
    processed_activity_ids,
    validate_activity,
)


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


class DispatchActivityTests(unittest.TestCase):
    def setUp(self) -> None:
        processed_activity_ids.clear()

    def test_new_valid_activity_is_processed(self) -> None:
        activity = {
            "id": "follow-1",
            "type": "Follow",
            "actor": "actor-1",
            "object": "actor-2",
        }
        output = StringIO()

        with redirect_stdout(output):
            dispatch_activity(activity)

        self.assertIn("Follow activity:", output.getvalue())
        self.assertIn("follow-1", processed_activity_ids)

    def test_second_attempt_is_rejected_as_duplicate(self) -> None:
        activity = {
            "id": "follow-1",
            "type": "Follow",
            "actor": "actor-1",
            "object": "actor-2",
        }
        output = StringIO()

        with redirect_stdout(output):
            dispatch_activity(activity)
            dispatch_activity(activity)

        lines = output.getvalue().splitlines()
        self.assertEqual(2, len(lines))
        self.assertTrue(lines[0].startswith("Follow activity:"))
        self.assertEqual("Duplicate activity", lines[1])


if __name__ == "__main__":
    unittest.main()
