# CampusDesk-DeployStaging SSM document contract

**Status:** ✅ Captured, reviewed, and templated. The tracked source is [CampusDesk-DeployStaging.template.json](CampusDesk-DeployStaging.template.json): an exact copy of version `1` except that the two image patterns contain `__AWS_ACCOUNT_ID__` instead of the account. [register-ssm-doc.sh](register-ssm-doc.sh) injects the account from STS at registration time. The live document remains authoritative if the two ever diverge.

**Related:** [CI_CD_SESSION_2_HANDOFF.md § Restricted SSM document](../../Docs/CI_CD_SESSION_2_HANDOFF.md), [campusdesk-deploy.sh](campusdesk-deploy.sh), [deploy-staging.yml](../../.github/workflows/deploy-staging.yml), [register-ssm-doc.sh](register-ssm-doc.sh).

## Identity

| Property | Value |
| --- | --- |
| Name | `CampusDesk-DeployStaging` |
| Version | `1` |
| Target type | `/AWS::EC2::Instance` |
| Format | Linux Command document |
| Schema | `2.2` |
| Invoked by | the environment-scoped deploy role, against the exact staging instance |

## Parameters

| Name | Type | Constraint | Meaning |
| --- | --- | --- | --- |
| `ReleaseSha` | String | `minChars`/`maxChars` 40, pattern `^[0-9a-f]{40}$` | the full `development` commit being deployed |
| `BackendImage` | String | `maxChars` 200, exact account, region, `campusdesk_backend`, and `@sha256:` digest | backend image reference |
| `FrontendImage` | String | `maxChars` 200, exact account, region, `campusdesk_frontend`, and `@sha256:` digest | frontend image reference |

All three parameters use `ENV_VAR` interpolation, which exposes them to the wrapper as `SSM_ReleaseSha`, `SSM_BackendImage`, and `SSM_FrontendImage`. The document contains one fixed `aws:runShellScript` step (`precondition` `platformType == Linux`, `timeoutSeconds` `1200`) that sets `set -eu` and `umask 077`, verifies the script exists and is executable, and then executes:

```
exec /usr/local/sbin/campusdesk-deploy \
  --release-sha "$SSM_ReleaseSha" \
  --backend-image "$SSM_BackendImage" \
  --frontend-image "$SSM_FrontendImage"
```

It accepts no arbitrary shell commands. The deploy role cannot invoke the general `AWS-RunShellScript` document.

## Re-capturing for review

The live document can be captured for comparison. Do not commit the captured file: it contains the account ID in the two image patterns. The deploy workflow invokes `--document-version "1"`, so capture version `1` rather than `$LATEST`.

```bash
aws ssm get-document \
  --name CampusDesk-DeployStaging \
  --document-version 1 \
  --region eu-north-1 \
  --query Content \
  --output text \
  --no-cli-pager > /tmp/CampusDesk-DeployStaging.json
```

On Windows, write the file as UTF-8 without a BOM rather than using `>`, which is UTF-16 in Windows PowerShell 5.1:

```powershell
$content = aws ssm get-document --name CampusDesk-DeployStaging --document-version 1 `
  --region eu-north-1 --query Content --output text --no-cli-pager
[System.IO.File]::WriteAllText("$env:TEMP\CampusDesk-DeployStaging.json", $content,
  (New-Object System.Text.UTF8Encoding($false)))
```

Compare the captured document with `CampusDesk-DeployStaging.template.json` after replacing `__AWS_ACCOUNT_ID__` with a dummy 12-digit value; the only expected difference is the account ID. If the live document differs in any other way, update the template, re-register it, and record the change.

## Registration

```bash
./ops/staging/register-ssm-doc.sh
```

The script resolves the account from STS, renders the template to a temporary file, updates version `1` (or creates it when absent), and sets the new version as the default. An identical-content update is treated as a no-op. The rendered file is deleted on exit and must never be committed.

Registration creates a new immutable document version. The deploy workflow deliberately pins `--document-version "1"` in [deploy-staging.yml](../../.github/workflows/deploy-staging.yml); a newly registered version is not used until that pin is updated in the same reviewed change. Record the new version in this file.

## Change control

- A change to the document or to the host script requires a separately reviewed host update; neither is synchronized by the deployment workflow.
- The document must keep the parameter constraints above. Widening them (for example, accepting an image outside the two repositories) would remove the deployment's main safety boundary.
- Any new parameter must be treated as security-sensitive: it is passed to a root-owned script.
- The template must never contain a real account ID, and a rendered copy must never be committed.
- A newly registered document version requires a matching `--document-version` update in the deploy workflow; the pin is intentional.
