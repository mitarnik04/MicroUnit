# Security Policy

## Supported versions

| Version | Supported |
| --- | --- |
| 1.x | Yes |
| 1.0.0 betas | No, please upgrade to 1.x |

## Reporting a vulnerability

Please don't report security problems in public issues or pull requests.

Send an email to **mitar.mn5@gmail.com** instead, with a description of the problem and, if you can, the steps to reproduce it. I'll confirm that I received it and work on a fix as fast as I can. Once a fix is released, I'm happy to credit you in the changelog if you want to be named.

Things worth reporting include anything that lets a test run or the Test Explorer execute code or read files it shouldn't. The explorer only listens on `127.0.0.1` and protects its API with a per-session token, so a way around either of those definitely counts.
