"""Small in-memory WebFinger discovery experiment."""

from urllib.parse import urlencode


def _split_account_identifier(account_identifier: str) -> tuple[str, str]:
    if (
        not isinstance(account_identifier, str)
        or account_identifier.count("@") != 1
    ):
        raise ValueError("account identifier must be in username@domain form")

    username, domain = account_identifier.split("@", 1)
    if not username or not domain or any(character.isspace() for character in account_identifier):
        raise ValueError("account identifier requires a username and domain")

    return username, domain


def build_webfinger_resource(account_identifier: str) -> str:
    username, domain = _split_account_identifier(account_identifier)
    return f"acct:{username}@{domain}"


def build_webfinger_lookup_url(account_identifier: str) -> str:
    _, domain = _split_account_identifier(account_identifier)
    resource = build_webfinger_resource(account_identifier)
    query = urlencode({"resource": resource})
    return f"https://{domain}/.well-known/webfinger?{query}"


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
    account_identifier = "alice@remote.example"
    resource = build_webfinger_resource(account_identifier)
    lookup_url = build_webfinger_lookup_url(account_identifier)
    document = build_webfinger_document(
        username="alice",
        domain="remote.example",
        profile_page_url="https://remote.example/@alice",
        activitypub_actor_url="https://remote.example/users/alice",
    )

    print("Account identifier:", account_identifier)
    print("WebFinger resource:", resource)
    print("Lookup URL:", lookup_url)
    print("ActivityPub actor:", find_activitypub_actor_url(document))
