"""Small in-memory WebFinger discovery experiment."""

import json


def build_webfinger_document(
    username: str,
    domain: str,
    profile_page_url: str,
    activitypub_actor_url: str,
) -> dict:
    return {
        "subject": f"acct:{username}@{domain}",
        "aliases": [profile_page_url, activitypub_actor_url],
        "links": [
            {
                "rel": "http://webfinger.net/rel/profile-page",
                "type": "text/html",
                "href": profile_page_url,
            },
            {
                "rel": "self",
                "type": "application/activity+json",
                "href": activitypub_actor_url,
            },
        ],
    }


def find_activitypub_actor_url(webfinger_document: dict) -> str | None:
    for link in webfinger_document.get("links", []):
        if (
            link.get("rel") == "self"
            and link.get("type") == "application/activity+json"
        ):
            return link.get("href")

    return None


if __name__ == "__main__":
    document = build_webfinger_document(
        username="alice",
        domain="remote.example",
        profile_page_url="https://remote.example/@alice",
        activitypub_actor_url="https://remote.example/users/alice",
    )

    print(json.dumps(document, indent=2))
    print("ActivityPub actor:", find_activitypub_actor_url(document))
