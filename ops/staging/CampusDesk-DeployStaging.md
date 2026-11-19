# CampusDesk-DeployStaging SSM document contract

**Status:** contract recorded from the implementation record and the deploy workflow; the exact document JSON has not yet been captured in this repository. Treat the live document as authoritative until the JSON is added and reviewed.

**Related:** [CI_CD_SESSION_2_HANDOFF.md § Restricted SSM document](../../Docs/CI_CD_SESSION_2_HANDOFF.md), [campusdesk-deploy.sh](campusdesk-deploy.sh), [deploy-staging.yml](../../.github/workflows/deploy-staging.yml).

## Identity

| Property | Value |
| --- | --- |
| Name | `CampusDesk-DeployStaging` |
| Version | `1` |
| Target type | `/AWS::EC2::Instance` |
| Format | Linux Command document |
| Invoked by | the environment-scoped deploy role, against the exact staging instance |

## Parameters

| Name | Type | Constraint | Meaning |
| --- | --- | --- | --- |
| `ReleaseSha` | String | exactly 40 lowercase hex characters | the full `development` commit being deployed |
| `BackendImage` | String | exact account, region, `campusdesk_backend`, and `@sha256:` digest | backend image reference |
| `FrontendImage` | String | exact account, region, `campusdesk_frontend`, and `@sha256:` digest | frontend image reference |

Parameters use `ENV_VAR` interpolation. The document contains a fixed `aws:runShellScript` wrapper that executes:

```
/usr/local/sbin/campusdesk-deploy \
  --release-sha "{{ReleaseSha}}" \
  --backend-image "{{BackendImage}}" \
  --frontend-image "{{FrontendImage}}"
```

It accepts no arbitrary shell commands. The deploy role cannot invoke the general `AWS-RunShellScript` document.

## Capture procedure

The exact JSON must be captured from the account before this file can be treated as a source of truth:

```bash
aws ssm get-document \
  --name CampusDesk-DeployStaging \
  --document-version '$LATEST' \
  --region eu-north-1 \
  --query Content \
  --output text > /tmp/CampusDesk-DeployStaging.json
```

Review the captured JSON, then add it as `ops/staging/CampusDesk-DeployStaging.json` and link it from this file.

## Change control

- A change to the document or to the host script requires a separately reviewed host update; neither is synchronized by the deployment workflow.
- The document must keep the parameter constraints above. Widening them (for example, accepting an image outside the two repositories) would remove the deployment's main safety boundary.
- Any new parameter must be treated as security-sensitive: it is passed to a root-owned script.
