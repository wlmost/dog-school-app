# deployment-pipeline

## Purpose

Defines behavior guarantees for the automated shared-hosting deployment
pipeline (`.github/workflows/deploy.yml`) — the CI/CD process that
synchronizes application files and runs post-deploy steps against a shared
hosting target after a merge to `main`.

## Requirements

### Requirement: Public storage symlink persists across automated shared-hosting deployments

The automated shared-hosting deployment workflow (`.github/workflows/deploy.yml`) SHALL NOT delete an already-existing `backend/public/storage` symlink on the target server while synchronizing files via rsync, and SHALL ensure the symlink exists after every deployment run — creating it if it is absent (e.g. on the very first deployment, or if it was previously removed) and leaving it untouched if it already exists.

This applies to every file served through the `public` filesystem disk via
`backend/public/storage`, not only dog profile images — including, but not
limited to, training-attachment uploads (`storage/app/public/training-attachments/`).

#### Scenario: Existing symlink survives an automated deploy
- **WHEN** an automated deployment runs via `.github/workflows/deploy.yml`
  against a server where `backend/public/storage` already exists as a valid
  symlink to `backend/storage/app/public`
- **THEN** the symlink still exists and still points to the same target after
  the deployment completes
- **THEN** files previously uploaded to `storage/app/public/...` remain
  reachable via their public URL after the deployment

#### Scenario: Missing symlink is created during an automated deploy
- **WHEN** an automated deployment runs via `.github/workflows/deploy.yml`
  against a server where `backend/public/storage` does not exist (first
  deployment, or the symlink was removed by an earlier, unfixed workflow run)
- **THEN** the deployment creates the symlink `backend/public/storage` →
  `backend/storage/app/public` before the deployment job finishes
  successfully
- **THEN** files uploaded after this deployment (e.g. a dog profile image
  or a training attachment) are reachable via their public URL

#### Scenario: Re-running the symlink step against an already-linked target does not fail the deploy
- **WHEN** the deployment's storage-symlink step runs against a server where
  the symlink already exists from a previous successful deployment
- **THEN** the deployment job does not fail or abort because of this step
- **THEN** the deploy job continues to the subsequent steps (database
  migrations, cache rebuild) as usual
