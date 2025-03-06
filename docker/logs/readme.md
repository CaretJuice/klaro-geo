# WordPress Debug Logs

This directory is mounted to the WordPress container's `/var/www/html/wp-content` directory to capture debug logs.

The WordPress debug log will be available at `debug.log` in this directory after running the container.

Do not delete this directory as it's required for the Docker setup.