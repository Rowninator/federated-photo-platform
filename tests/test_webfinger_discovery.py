import unittest

from experiments.webfinger_discovery import (
    build_webfinger_document,
    build_webfinger_lookup_url,
    build_webfinger_resource,
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

    def test_builds_webfinger_resource(self) -> None:
        self.assertEqual(
            "acct:alice@remote.example",
            build_webfinger_resource("alice@remote.example"),
        )

    def test_builds_encoded_webfinger_lookup_url(self) -> None:
        self.assertEqual(
            "https://remote.example/.well-known/webfinger"
            "?resource=acct%3Aalice%40remote.example",
            build_webfinger_lookup_url("alice@remote.example"),
        )

    def test_malformed_account_identifiers_raise_value_error(self) -> None:
        malformed_identifiers = (
            "alice",
            "@remote.example",
            "alice@",
            "alice@remote@example",
        )

        for account_identifier in malformed_identifiers:
            for builder in (build_webfinger_resource, build_webfinger_lookup_url):
                with self.subTest(
                    account_identifier=account_identifier,
                    builder=builder.__name__,
                ):
                    with self.assertRaises(ValueError):
                        builder(account_identifier)


if __name__ == "__main__":
    unittest.main()
