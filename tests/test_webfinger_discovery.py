import unittest

from experiments.webfinger_discovery import (
    build_webfinger_document,
    find_activitypub_actor_url,
)


class WebFingerDiscoveryTests(unittest.TestCase):
    def setUp(self) -> None:
        self.actor_url = "https://remote.example/users/alice"
        self.document = build_webfinger_document(
            username="alice",
            domain="remote.example",
            profile_page_url="https://remote.example/@alice",
            activitypub_actor_url=self.actor_url,
        )

    def test_builds_expected_acct_subject(self) -> None:
        self.assertEqual("acct:alice@remote.example", self.document["subject"])

    def test_contains_activitypub_self_link(self) -> None:
        self.assertTrue(
            any(
                link.get("rel") == "self"
                and link.get("type") == "application/activity+json"
                for link in self.document["links"]
            )
        )

    def test_finds_activitypub_actor_url(self) -> None:
        self.assertEqual(
            self.actor_url,
            find_activitypub_actor_url(self.document),
        )

    def test_returns_none_without_matching_self_link(self) -> None:
        document = {
            "links": [
                {
                    "rel": "self",
                    "type": "text/html",
                    "href": "https://remote.example/@alice",
                }
            ]
        }

        self.assertIsNone(find_activitypub_actor_url(document))


if __name__ == "__main__":
    unittest.main()
