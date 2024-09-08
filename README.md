# JEDI

JEDI is a service that synchronizes member information from [Apiary](https://github.com/RoboJackets/apiary) to other RoboJackets services. This enables new members to quickly and easily get access to the services they need to participate in RoboJackets, and automatically removes access from people who are no longer participating.

It operates completely transparently to the general membership, and most members will never interact with it directly or even know it exists.

Most changes are applied within 5 seconds of the change being saved in Apiary.

## Local development

A `Dockerfile` and `docker-compose.yml` file are included in this repository. Both are designed for use on Linux hosts; you may need to make adjustments to run on macOS or Windows.

The `Dockerfile` is intended for production release but can also be used for local development. Run `docker compose up --build` to build and start the container. A Sanctum token will be printed during container startup. If you make code changes, you will need to stop any existing containers and run `docker compose up --build` again to rebuild and restart it.

## Supported applications and services

The following external services can receive updates from JEDI.

- ClickUp
- GitHub
- Google Groups
- Keycloak
- Nextcloud
- Shared User Management System (SUMS)
- WordPress

## Supported membership changes

This section broadly describes what JEDI can change within other services when events occur.

### When a member pays dues or receives an access override

- They will be invited to the RoboJackets ClickUp workspace, if they have provided an email address for this purpose
- They will be invited to the RoboJackets organization in GitHub, if they have a linked GitHub account
- They will be added to the Google Groups corresponding to their teams, if they have a linked Google account
- Their Keycloak account will be enabled, if it already exists in Keycloak
- Their Nextcloud account will be enabled, if it already exists in Nextcloud, and group membership will be synchronized with Apiary
- They will be added to the RoboJackets billing group in SUMS
- Their WordPress account will be enabled, if it already exists in WordPress, and they are in the appropriate Apiary team

### When a member's access expires

This typically happens when a member stops paying dues. [Officers may configure a grace period using the Access End Date on packages](https://my.robojackets.org/docs/officers/dues/setup/#set-dues-deadlines).

- They will be removed from the RoboJackets ClickUp workspace, if they have provided an email address for this purpose
- They will be removed from the RoboJackets organization in GitHub, if they have a linked GitHub account
- They will be removed from all Google Groups, if they have a linked Google account
- Their Keycloak account will be disabled, if it already exists in Keycloak
- Their Nextcloud account will be disabled, if it already exists in Nextcloud, and they will be removed from all groups
- They will be removed from the RoboJackets billing group in SUMS
- Their WordPress account will be disabled, if it already exists in WordPress

### When a member joins or leaves a team

> [!NOTE]  
> Due to a bug in Apiary, team changes made in Nova do not trigger updates. Users with sufficient permissions can force a sync using the **Sync Access** action available on users.

- Their Nextcloud groups will be synchronized
- They will be added to any applicable teams in GitHub, if they have a linked GitHub account
