# Security Policy

## Supported versions

The latest release on the `main` branch is the one that receives fixes.

## Reporting a vulnerability

Please do not open a public issue for a vulnerability.

Use GitHub's [private vulnerability
reporting](https://github.com/dardan-gashi/filament-api-explorer/security/advisories/new)
— it opens a private thread with the maintainer and becomes the draft advisory
if the report holds. If you would rather write an email: REDACTED.

Please include the version, what an attacker gains, and the smallest way to
reproduce it. You will get an answer within a week.

## What is in scope

This package renders an OpenAPI document inside a Filament panel and can send
`GET` requests on the reader's behalf. Reports about that surface are in scope —
for instance a way past `execution.allowed_hosts` or `allowed_schemes`, a
credential leaking into a rendered sample or a cached example, unescaped
document content reaching the page as markup, or a way to open the page without
passing the panel's own authorization.

Two things are documented behaviour rather than vulnerabilities:

- **Recorded examples hold real response data** and are shown to everyone who
  can open the page. That is what `examples.capture` switches off.
- **The request sender reaches the hosts you list.** Naming a production host in
  `execution.allowed_hosts` is what makes production callable from that panel.

Both are described in the README under *Sending requests* and *Recorded
examples*.
