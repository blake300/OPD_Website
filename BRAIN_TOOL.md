# brain_tool quick start

This repo is configured to use the shared `brain_tool` CLI.

## Run from terminal
- `brain_tool --help`
- `brain_tool env`
- `brain_tool use --task "<describe the task>"`

## Common workflows
- Smarter_Library: `brain_tool learn_library` then `brain_tool library_report`
- Repo review: `brain_tool review_repo --workspace .`
- Fixes: `brain_tool fix_repo --workspace .` (only when explicitly approved)

## IDE agents
- Codex: say ?use brain_tool? and it will invoke the skill.
- Claude Code: `CLAUDE.md` imports shared instructions.
- Copilot: `.github/copilot-instructions.md` contains the instructions.
