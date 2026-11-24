# CampusDesk — Phase 3: Reproducible Release Builds

**Last reviewed:** 24 November 2026

**Status:** Fix PR #42 and the Dependabot updates are merged; `4a13764` is the latest release. Deploy and reproducibility verification are pending.

**Scope:** [Frontend/Dockerfile](../Frontend/Dockerfile), [campusdesk/Dockerfile](../campusdesk/Dockerfile), [.github/workflows/build-images.yml](../.github/workflows/build-images.yml), [.github/workflows/verify-reproducibility.yml](../.github/workflows/verify-reproducibility.yml), [.github/dependabot.yml](../.github/dependabot.yml), and the CI documentation.

**Related:** [CI/CD Hardening Plan](CI_CD_HARDENING_PLAN.md), [Phase 1 design](CI_CD_PHASE_1_DESIGN.md), [Phase 2 design](CI_CD_PHASE_2_DESIGN.md), [CI/CD implementation and operations guide](CI_CD_SESSION_2_HANDOFF.md), [CI-CD.md](../CI-CD.md).

## 1. Goal and non-goals

**Goal:** the release image is built from pinned inputs, its application content is reproducible, and the published image carries a verifiable provenance attestation and SBOM. The tested artifact and the shipped artifact are the same digest.

**Non-goals:** bit-for-bit identical image digests (timestamps and layer ordering are outside scope), OS-package reproducibility beyond the pinned base image, and vulnerability remediation. Vulnerability scanning is a Phase 5 task.

## 2. Decisions

| Decision | Choice |
| --- | --- |
| Attestation/publication order | Option A: publish the final SHA tag with attestations, then pull that digest and smoke-test it |
| Base image maintenance | Dependabot for the Docker and GitHub Actions ecosystems |
| Reproducibility check | Separate `workflow_dispatch`, calling the reusable build twice and comparing file hashes |
| Provenance mode | `mode=max` with `sbom: true` |
| Vulnerability scan | Deferred to Phase 5 |

## 3. Pinned inputs

Pin the **index digest** of each official image. The digests below were retrieved and verified on 24 November 2026; Dependabot updates them.

| Reference | Index digest |
| --- | --- |
| `node:22-alpine` | `sha256:1314fb30ec37592d5845e5a160d522c0e680972aa534549940b8d73bde88481a` |
| `nginx:alpine` | `sha256:b50897d5c7b1aa08067c3d90c5e8b3e47ec4e3c91c91446f67820228f7b41e34` |
| `php:8.3-apache` | `sha256:499556533c2a9d0614b51be13ade91120181abfd4ca5abb4063b5f6a68b8834e` |
| `composer:2` | `sha256:cd3a90dc5624c59a2f9ddf80dad08569f06416db31de519024f249d63bca554b` |

Dockerfile changes:

```dockerfile
# Frontend
FROM node:22-alpine@sha256:1314fb30ec37592d5845e5a160d522c0e680972aa534549940b8d73bde88481a AS build
...
FROM nginx:alpine@sha256:b50897d5c7b1aa08067c3d90c5e8b3e47ec4e3c91c91446f67820228f7b41e34
```

```dockerfile
# Backend
FROM composer:2@sha256:cd3a90dc5624c59a2f9ddf80dad08569f06416db31de519024f249d63bca554b AS composer
FROM php:8.3-apache@sha256:499556533c2a9d0614b51be13ade91120181abfd4ca5abb4063b5f6a68b8834e
...
COPY --from=composer /usr/bin/composer /usr/bin/composer
```

The composer image becomes an explicit pinned stage because the previous `COPY --from=composer:2` was an external image reference that the "every `FROM` line" check would miss.

**Dependabot** (`.github/dependabot.yml`) watches `docker` in `/Frontend` and `/campusdesk` and `github-actions` at `/`, weekly. A digest-pin PR is validated by the normal PR image build.

## 4. Deterministic application content

Pinned bases plus existing lockfiles give deterministic application files:

- `npm ci` from `package-lock.json` for the frontend;
- `composer install --no-dev` from `composer.lock` for the backend;
- fixed `VITE_API_URL` and `VITE_REVERB_APP_KEY` build args;
- Vite content hashes for the JS/CSS assets.

The `org.opencontainers.image.created` label still varies between builds; it does not change application file content.

**Residual:** `apt-get install unzip` reaches live Debian repositories, so the backend OS layer is not fully reproducible. That is outside this phase's acceptance; the frontend and backend application files are the targets.

## 5. Attested release (Option A)

In `build-images.yml`, the release path now builds and pushes in one step:

```yaml
with:
  push: true
  load: false
  provenance: mode=max
  sbom: true
  tags: ${{ steps.meta.outputs.tag }}
```

The tag therefore refers to an OCI image index containing the image manifest, the provenance attestation, and the SBOM attestation.

After the push, the smoke test pulls the exact pushed digest and runs it:

```bash
docker pull "${REGISTRY}/${REPOSITORY}@${DIGEST}"
docker run ... "${REGISTRY}/${REPOSITORY}@${DIGEST}"
```

The `digest` comes from the build step (`docker/build-push-action` output) and is cross-checked against the ECR tag. The `Publish` step no longer runs `docker push`; it resolves and validates the digest.

If the smoke test fails, the job fails, `release-manifest` is skipped, and the immutable SHA tag remains but no release is deployable. A fix requires a new commit. This is the documented quarantine rule; the release manifest remains the deployment gate.

If the immutable tag already exists, the build step is skipped, but the existing digest is pulled and smoke-tested before the manifest is written. A rerun therefore cannot publish an image that never passed.

The publisher role needs `ecr:GetDownloadUrlForLayer` on both repositories (in addition to `ecr:BatchGetImage`) because the smoke test pulls the pushed layers from ECR. This is a Phase 3 IAM prerequisite.

The PR path keeps `push: false`, `load: true`, no attestations, and no ECR access.

## 6. Reproducibility workflow

`verify-reproducibility.yml` is a separate `workflow_dispatch`:

- `build-a` calls `build-images.yml` with `push: false` and `file_hash_label: a`;
- `build-b` calls it with `push: false` and `file_hash_label: b`;
- `compare` downloads both artifacts and fails on any difference.

`build-images.yml` gains an optional `file_hash_label` input. When non-empty:

- the build sets `no-cache: true`;
- after the smoke test, the backend writes `sha256sum artisan composer.lock vendor/composer/installed.php` and the frontend writes `sha256sum /usr/share/nginx/html/index.html /usr/share/nginx/html/assets/index-*.js`;
- each job uploads `file-hashes-<label>-backend` or `file-hashes-<label>-frontend` (7-day retention).

Both calls use the same reusable recipe, so the check does not duplicate the build definition.

### Trade-offs

| Benefit | Cost / limitation |
| --- | --- |
| Opt-in proof that the same commit produces identical application files | Runs only when dispatched; a regression can merge before anyone runs it |
| Same reusable recipe, Dockerfiles, contexts, and arguments | Adds a gated file-hash/artifact path to `build-images.yml` |
| Separate cold runners plus `--no-cache` give a clean comparison | Two full builds of both images, roughly 4–8 minutes of CI |
| Artifacts make the comparison auditable | Artifact retention/storage |
| No registry access or publication risk | Does not prove bit-for-bit image digests or the attested release path |
| Can be run before merge | Advisory, not a required check; no automatic merge block |

The check is deliberately not per-PR: doubling every PR's image time would be expensive, and the normal PR build already validates that the recipe works. A weekly schedule or a required check can be added later if drift becomes a concern.

## 7. Provenance `mode=max` and SBOM

BuildKit creates an in-toto provenance attestation and attaches it to the image index. `min` records minimal build metadata; `max` records the full build definition, including build arguments and materials. `sbom: true` attaches a Syft-generated SPDX SBOM.

`docker buildx imagetools inspect <ecr>/<repo>:<sha>` displays the Provenance and SBOM sections; `docker pull` by index digest resolves the image normally.

### Trade-offs

| Benefit | Cost / limitation |
| --- | --- |
| Auditors see the arguments and materials that produced the image | Build arguments become public; never pass secrets as build args |
| Supports reproducibility and incident questions without rebuilding | Larger attestation and more registry storage |
| SBOM feeds the Phase 5 vulnerability scan | Switching from `min` to `max` changes the index digest; the release manifest records the new digest |
| No runtime or application change | The attestation is unsigned by default; authenticity depends on the push credentials, and signing is a later step |

The only current build args are `VITE_API_URL=/api` and the non-secret `VITE_REVERB_APP_KEY=campusdesk-key`. Phase 4 removes the Reverb key. The standing rule is: **no secret may ever be passed as a Docker build argument**.

## 8. Model A

Add to [CI-CD.md](../CI-CD.md):

- the release identity is the `development` commit SHA;
- a pull request builds both images only to validate the recipe and never pushes;
- a `development` push builds that commit once, publishes it with provenance and SBOM, and writes the release manifest;
- deployments consume the manifest and pull the recorded digests; they never rebuild;
- a PR merge ref and its merge commit are different SHAs by design, but the recipe is identical.

## 9. Manifest, digests, and deployment

With attestations, the ECR tag digest is the **index digest**. The reusable workflow output, the release manifest, and `release.json` all record that digest. The host pulls `repo@sha256:<index>`; the revision label and digest checks are unchanged.

## 10. Vulnerability scan

Deferred to Phase 5. Phase 3 removes the optional Phase 3 scan task and adds a Phase 5 task to scan the pushed digest with Trivy/Grype and record the report. The provenance and SBOM produced here are the inputs to that scan.

## 11. Acceptance tests

- Every external image reference is pinned: every `FROM` and external `COPY --from` has `@sha256:` (the composer reference is now a stage).
- Two dispatched builds of the same commit produce identical frontend file hashes (`index.html` and `index-*.js`) and identical backend application file hashes.
- `docker buildx imagetools inspect <ecr>/<repo>:<sha>` shows both **Provenance** and **SBOM**.
- The release manifest and `release.json` digests match the ECR tags.
- The smoke test uses the pushed digest, and the recorded digest equals the tested digest.
- An existing immutable tag is pulled and smoke-tested before the release manifest is written.
- The PR path still makes no ECR call, no OIDC assumption, and no attestations.
- The deploy of the release SHA succeeds with the index digest and the record verifies.

## 12. Rollout and test plan

1. Open the PR; the PR run builds both pinned images and runs the local smoke tests with no ECR.
2. Merge; the push run builds/pushes with `provenance: mode=max` and `sbom: true`, then pulls the pushed digest and smoke-tests it.
3. `release-manifest` writes the index digests; verify `/campusdesk/releases/<sha>` and `/campusdesk/staging/latest`.
4. Run `docker buildx imagetools inspect` on both images; confirm Provenance and SBOM.
5. Deploy the merge SHA normally; verify the manifest, the record, and the running revision label.
6. Dispatch `verify-reproducibility.yml` for the merge SHA; confirm the compare job passes.
7. Optionally rerun CI for the same SHA to confirm the existing-tag path pulls and smoke-tests the digest without rebuilding, then reuses it.

## 13. Risks and decisions

| Risk / decision | Position |
| --- | --- |
| Publication before smoke | Accept; the manifest gate prevents deployment, and the immutable tag is inert. The quarantine rule is documented. |
| Publisher pull permission | The ECR push policy must include `ecr:GetDownloadUrlForLayer` on both repositories; the smoke test pulls the pushed digest. |
| Existing-tag rerun | The build is skipped, but the existing digest is pulled and smoke-tested before publication. |
| Index digest vs manifest digest | Verify the ECR tag digest equals the build output, the release manifest, and `release.json`; the host pulls the index. |
| Base image drift | Dependabot updates pinned digests; the PR build validates every pin. |
| Apt package drift | Out of scope; documented residual for the backend OS layer. |
| `mode=max` exposure | Build args are non-secret; no secret may be a build arg; Phase 4 removes the Reverb key. |
| Unsigned attestations | Integrity comes from the index digest and registry access; signing is a later hardening step. |
| Reproducibility coverage | Advisory dispatch; can be scheduled or made required later. |
| PR path attestations | None; `load: true` cannot carry them and the PR never publishes. |

## 14. PR

One PR, `ci/reproducible-builds`:

- digest pins and the composer stage in both Dockerfiles;
- `provenance: mode=max`/`sbom: true` on the release build, with the pushed-digest smoke test;
- the optional file-hash support and `verify-reproducibility.yml`;
- `.github/dependabot.yml`;
- the `CI-CD.md` Model A section;
- the plan and handoff updates.

No host scripts, SSM documents, or deploy workflow changes.
