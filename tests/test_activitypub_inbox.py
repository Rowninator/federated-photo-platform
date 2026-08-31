import unittest
from contextlib import redirect_stdout
from io import StringIO

from experiments.activitypub_inbox import (
    accept_follow_request,
    build_accept_activity,
    build_reject_activity,
    dispatch_activity,
    local_profiles,
    outbound_activities,
    process_accept_follow_request,
    process_reject_follow_request,
    processed_activity_ids,
    reject_follow_request,
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


class FollowRequestDecisionTests(unittest.TestCase):
    def setUp(self) -> None:
        local_profiles.clear()

        self.actor_url = "https://remote.example/users/alice"
        self.profile_url = "https://social.example/users/bob"
        local_profiles[self.profile_url] = {
            "is_private": True,
            "followers": set(),
            "pending_follow_requests": {self.actor_url},
        }

    def test_accept_pending_follow_request(self) -> None:
        accept_follow_request(self.profile_url, self.actor_url)

        profile = local_profiles[self.profile_url]
        self.assertNotIn(self.actor_url, profile["pending_follow_requests"])
        self.assertIn(self.actor_url, profile["followers"])

    def test_reject_pending_follow_request(self) -> None:
        reject_follow_request(self.profile_url, self.actor_url)

        profile = local_profiles[self.profile_url]
        self.assertNotIn(self.actor_url, profile["pending_follow_requests"])
        self.assertNotIn(self.actor_url, profile["followers"])

    def test_accept_nonexistent_pending_request_does_not_change_state(self) -> None:
        unknown_actor = "https://remote.example/users/unknown"

        accept_follow_request(self.profile_url, unknown_actor)

        profile = local_profiles[self.profile_url]
        self.assertEqual({self.actor_url}, profile["pending_follow_requests"])
        self.assertEqual(set(), profile["followers"])


class FollowResponseBuilderTests(unittest.TestCase):
    def setUp(self) -> None:
        self.local_actor = "https://social.example/users/bob"
        self.follow_activity = {
            "id": "https://remote.example/activities/follow-1",
            "type": "Follow",
            "actor": "https://remote.example/users/alice",
            "object": self.local_actor,
        }

    def test_accept_has_expected_type_and_actor(self) -> None:
        activity = build_accept_activity(
            "https://social.example/activities/accept-1",
            self.local_actor,
            self.follow_activity,
        )

        self.assertEqual("Accept", activity["type"])
        self.assertEqual(self.local_actor, activity["actor"])

    def test_reject_has_expected_type_and_actor(self) -> None:
        activity = build_reject_activity(
            "https://social.example/activities/reject-1",
            self.local_actor,
            self.follow_activity,
        )

        self.assertEqual("Reject", activity["type"])
        self.assertEqual(self.local_actor, activity["actor"])

    def test_builders_preserve_original_follow_activity(self) -> None:
        accept = build_accept_activity(
            "https://social.example/activities/accept-1",
            self.local_actor,
            self.follow_activity,
        )
        reject = build_reject_activity(
            "https://social.example/activities/reject-1",
            self.local_actor,
            self.follow_activity,
        )

        self.assertEqual(self.follow_activity, accept["object"])
        self.assertEqual(self.follow_activity, reject["object"])


class FollowRequestProcessingTests(unittest.TestCase):
    def setUp(self) -> None:
        local_profiles.clear()
        outbound_activities.clear()

        self.actor_url = "https://remote.example/users/alice"
        self.profile_url = "https://social.example/users/bob"
        self.destination = "https://remote.example/users/alice/inbox"
        self.follow_activity = {
            "id": "https://remote.example/activities/follow-1",
            "type": "Follow",
            "actor": self.actor_url,
            "object": self.profile_url,
        }
        local_profiles[self.profile_url] = {
            "is_private": True,
            "followers": set(),
            "pending_follow_requests": {self.actor_url},
        }

    def test_accept_returns_activity_and_updates_state(self) -> None:
        response = process_accept_follow_request(
            self.profile_url,
            self.follow_activity,
            "https://social.example/activities/accept-1",
        )

        profile = local_profiles[self.profile_url]
        self.assertEqual("Accept", response["type"])
        self.assertIs(self.follow_activity, response["object"])
        self.assertNotIn(self.actor_url, profile["pending_follow_requests"])
        self.assertIn(self.actor_url, profile["followers"])

    def test_reject_returns_activity_and_updates_state(self) -> None:
        response = process_reject_follow_request(
            self.profile_url,
            self.follow_activity,
            "https://social.example/activities/reject-1",
        )

        profile = local_profiles[self.profile_url]
        self.assertEqual("Reject", response["type"])
        self.assertIs(self.follow_activity, response["object"])
        self.assertNotIn(self.actor_url, profile["pending_follow_requests"])
        self.assertNotIn(self.actor_url, profile["followers"])

    def test_nonexistent_pending_request_returns_none_without_state_change(self) -> None:
        unknown_follow = self.follow_activity | {
            "actor": "https://remote.example/users/unknown"
        }

        accept_response = process_accept_follow_request(
            self.profile_url,
            unknown_follow,
            "https://social.example/activities/accept-1",
        )
        reject_response = process_reject_follow_request(
            self.profile_url,
            unknown_follow,
            "https://social.example/activities/reject-1",
        )

        profile = local_profiles[self.profile_url]
        self.assertIsNone(accept_response)
        self.assertIsNone(reject_response)
        self.assertEqual({self.actor_url}, profile["pending_follow_requests"])
        self.assertEqual(set(), profile["followers"])

    def test_accept_queues_one_accept_activity(self) -> None:
        response = process_accept_follow_request(
            self.profile_url,
            self.follow_activity,
            "https://social.example/activities/accept-1",
            self.destination,
        )

        self.assertEqual(1, len(outbound_activities))
        self.assertIs(response, outbound_activities[0]["activity"])
        self.assertEqual("Accept", outbound_activities[0]["activity"]["type"])

    def test_reject_queues_one_reject_activity(self) -> None:
        response = process_reject_follow_request(
            self.profile_url,
            self.follow_activity,
            "https://social.example/activities/reject-1",
            self.destination,
        )

        self.assertEqual(1, len(outbound_activities))
        self.assertIs(response, outbound_activities[0]["activity"])
        self.assertEqual("Reject", outbound_activities[0]["activity"]["type"])

    def test_queued_destination_is_preserved(self) -> None:
        process_accept_follow_request(
            self.profile_url,
            self.follow_activity,
            "https://social.example/activities/accept-1",
            self.destination,
        )

        self.assertEqual(self.destination, outbound_activities[0]["destination"])

    def test_nonexistent_pending_request_queues_nothing(self) -> None:
        unknown_follow = self.follow_activity | {
            "actor": "https://remote.example/users/unknown"
        }

        response = process_accept_follow_request(
            self.profile_url,
            unknown_follow,
            "https://social.example/activities/accept-1",
            self.destination,
        )

        self.assertIsNone(response)
        self.assertEqual([], outbound_activities)


if __name__ == "__main__":
    unittest.main()
