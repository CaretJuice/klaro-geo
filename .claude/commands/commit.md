# Test and Commit Command

Run the test suite and commit changes if all tests pass. Claude generates the commit message based on changes.

## Workflow

1. **Run the test suite** in Docker:
   ```bash
   cd docker && docker compose run --rm wordpress_test vendor/bin/phpunit
   ```

2. **Check test results**:
   - If ANY tests fail: Stop and report the failures. Do NOT commit.
   - If ALL tests pass: Proceed to step 3.

3. **Analyze changes** to generate commit message:
   - Run `git status` and `git diff --staged` (or `git diff` if not staged)
   - Determine the type: fix, feat, test, docs, refactor, chore
   - Write a concise commit message summarizing the changes

4. **Stage changes**:
   - Stage all relevant changes (exclude temporary files, logs, etc.)
   - Do NOT stage: `docker/logs/*`, `*.log`, `node_modules/`, `vendor/`

5. **Create the commit**:
   - Generate an appropriate commit message based on the changes
   - Include the standard footer

6. **Report results**:
   - Show the commit hash and message
   - Show a brief summary of files changed

## Commit Message Format

```
<type>: <short description>

<optional body with more details>

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>
```

Types:
- `fix:` - Bug fixes
- `feat:` - New features
- `test:` - Test additions/changes
- `docs:` - Documentation changes
- `refactor:` - Code refactoring
- `chore:` - Maintenance tasks

## Important Notes

- NEVER commit if tests fail
- NEVER commit secrets, credentials, or sensitive data
- Generate the commit message automatically based on changes
- After committing, show the result with `git log -1 --oneline`
