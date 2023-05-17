## Drush Queue Daemon

### drush-queue.service example

```
[Unit]
Description=Run drush queue as daemon

[Service]
WorkingDirectory=/var/www/example/current
ExecStart=/var/www/example/current/vendor/bin/drush -l https://www.example.de queue:run:daemon --timeout 300 webhooks_dispatcher
Restart=always
RestartSec=10s
User=www-data
Group=www-data

[Install]
WantedBy=multi-user.target
```
