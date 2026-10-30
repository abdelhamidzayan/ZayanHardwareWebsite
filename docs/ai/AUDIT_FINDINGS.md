# Full-System Audit Findings

Audit branch: `audit/full-system-20261030`  
Source baseline: `feature/profiles-help-media` at `1752f1b4c2acb48c5fbad182a0a3e82cace23c48`  
Audit code commit: `f455dcab3818c5d19a9aa2487929e937f8636b14`

## Confirmed issue fixed

### Route parameter type mismatch — avatar URL
- Route: `GET /avatar/{userId}` in `config/routes.php`.
- Target: `AvatarController::show(int $userId)`.
- Previous behavior: Router matched all placeholders as strings except the special name `id`, then cast only `id`.
- Result: under `strict_types=1`, invoking the typed method with a string could raise `TypeError`; non-numeric values also matched the route pattern.
- Fix: Router declares `INTEGER_ROUTE_PARAMETERS = ['id', 'userId']`, restricts those segments to digits and normalizes them to integers. `replyId` and `updateId` remain strings because their controller actions validate them with `FILTER_VALIDATE_INT`; draft/media UUIDs and usernames also remain strings.
- Regression test: `tests/router-route-parameters.php` covers numeric matching for `id` and `userId`, their integer conversion, controller-level validation of string `replyId` and `updateId`, and preservation of UUID/text parameters.
- Test status: passed in GitHub Actions on audit commit `74360aba7fb9b916aa3ccfd702f10bac73794090` (`41131202664`).

## Graphify audit quality issue fixed
The prior audit script calculated static consistency failures but did not return a failing exit code. It also printed source extraction failures as warnings only. The hardened script now exits unsuccessfully on either class of failure, so CI cannot report a green audit when those checks fail.

## Route contract review
The explicit dynamic route registrations were compared with their controller method signatures. The integer-normalized placeholders are `id` and `userId`; `replyId` and `updateId` are intentionally strings validated inside the controller methods, while draft/media UUIDs and usernames remain strings. The Graphify static checker now repeats this comparison on every workflow run.

## Existing concern — migration numbering
There are two migration files beginning with `002_`: `002_final_registration_permissions.sql` and `002_registration_wizard.sql`. Keep their explicit order: the first creates account/skill tables and transfers the legacy specialization; the second moves legacy phone and experience into the normalized fields before dropping the old columns. PR #14 now tests this sequence on MySQL 8.0 and MariaDB 10.11. This does not establish which sequence has already been applied to the production database; inspect live schema and migration history before any deployment.

## Verification not yet available
- No local PHP CLI, production database, browser, mobile or PWA test was executed in this chat. CI PHP syntax, route regression, and migration integration checks passed on the audited commit.
- Graphify completed successfully on audit commit `74360aba7fb9b916aa3ccfd702f10bac73794090` (`41131202655`). The verified run summary reported 119 PHP/JS/SQL source files, 84 route targets, 26 controller-view links, 962 graph nodes, 1,812 relations, and zero missing or dangling relation endpoints. [Open the Graphify run and artifact](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41131202655).
- The curated `GRAPH.md` is manual/source-derived. It must not be labeled as Graphify output.
- Production deployment commit, live schema level, server permissions and actual local working-tree state remain unverified.

## Scope of next review
Inspect actual Graphify output, then continue the feature slices: authentication/account lifecycle; issue publishing/replies/diagnostics; draft upload and publish rollback; private help/publication consent; avatar and attachment delivery; admin authorization; notifications/Web Push; PWA cache; migration compatibility. Run DB/browser/integration tests where the environment allows.
