# Assets Directory

This directory contains static assets (images, screenshots, etc.) used in the `readme.md` file.

## Usage

Place images here and reference them in `readme.md` using relative paths:

```markdown
![Alt text](assets/image-name.png)
```

## Build Process

The `build-plugin.sh` script automatically copies this directory to the build output, so images will be included in the plugin distribution package.
