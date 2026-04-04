# WordPress Blog + Coolify + Auto Posting

This repo gives you two paths:

- Local development with Docker Compose
- Coolify deployment with WordPress, MySQL, and an optional auto-posting worker

## Files

- `compose.yaml` for local development
- `compose.coolify.yaml` for Coolify
- `autopost/` contains the worker that generates and submits posts to WordPress

## Run locally

```powershell
docker compose up -d
```

Open `http://localhost:8080` and finish the WordPress installer.

If you also want the auto-posting worker locally:

```powershell
docker compose --profile autopost up -d
```

## Coolify deployment

1. Create a new application in Coolify using the Docker Compose build pack.
2. Point it at this repository.
3. Set Base Directory to `/` and Docker Compose Location to `compose.coolify.yaml`.
4. Assign a domain only to the `wordpress` service.
5. Do not expose `db` or `autopost` publicly.
6. Set the environment variables from [`.env.example`](./.env.example) in Coolify.
7. Deploy once, finish the WordPress web installer, then create the bot credentials.
8. Redeploy after you add the auto-posting secrets.

If Coolify asks for the application port, use `80` for the `wordpress` service.

## Auto-posting setup

The `autopost` service publishes through the WordPress REST API using an Application Password.

Set these values in Coolify:

- `AUTOPOST_ENABLED=1`
- `AUTOPOST_STATUS=draft` for the first runs
- `AUTOPOST_WORDPRESS_URL=https://your-domain.com`
- `AUTOPOST_WORDPRESS_USERNAME=<wordpress-username>`
- `AUTOPOST_WORDPRESS_APP_PASSWORD=<application-password>`
- `OPENAI_API_KEY=<your-openai-key>`
- `OPENAI_MODEL=gpt-5-mini`
- `AUTOPOST_TOPICS=topic one,topic two,topic three`

Recommended first run:

- Keep `AUTOPOST_STATUS=draft`
- Set `AUTOPOST_RUN_ONCE=1`
- Deploy and review the created draft in WordPress
- Change `AUTOPOST_RUN_ONCE=0` when you are happy with the output

## Reset local data

```powershell
docker compose down -v
```

## Notes

- The worker stores its last-run state in a Docker volume so it does not repost on every restart.
- For production, point `AUTOPOST_WORDPRESS_URL` to your public HTTPS domain.
- Application Passwords are meant for integrations, not browser login.
