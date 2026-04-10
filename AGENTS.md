# Project Instructions

## Product Context

This project is a complete hotel management system intended for production use.

Work with that level of care:
- Do not guess about business rules, workflows, data shape, or expected behavior.
- If something is unclear or risky, stop and ask before implementing.
- Favor correctness, stability, and maintainability over speed.

## Execution Standards

- Treat every change as production-facing unless clearly marked otherwise.
- Keep explanations concise and useful.
- Avoid long, low-signal descriptions of functions or code unless deeper detail is requested.
- The intended UI direction is professional, modern, and credible for a real hotel operation.
- Prefer polished, practical interfaces over flashy or experimental ones.

## Decision Making

- Verify assumptions in code, schema, routes, and existing patterns before changing behavior.
- If a requirement is ambiguous, clarify it instead of inventing a solution.
- Do not introduce speculative logic just to move forward.

## UX Interaction Standards

- Summary cards must be clickable when they represent navigable data and should link to the page that contains the underlying records or details.
- Where possible, linked summary cards should open the destination page with filters, query parameters, or state that match the summarized data and improve information access.
- Submit actions must be protected against double submission. Buttons should enter a non-repeatable loading or disabled state immediately after the first valid submission until the request completes or fails.
- Forms and other async actions must provide clear feedback that data is being sent, processed, or that the next page is loading.
- Use visible loading indicators for navigation and async workflows, such as progress bars, inline loaders, button loading states, or page-level skeletons, based on the context.

## Git And Commit Rules

- After every meaningful change set, create a git commit before moving to the next task.
- Keep commits small, focused, and easy to review.
- Do not batch unrelated changes into one commit.
- A meaningful change set may be one bug fix, one schema change, one API resource, one UI screen, or one workflow step.
- After a migration, a stable feature slice, or a major refactor, commit the work once it is verified and coherent.
- Before starting a new sub-task, check `git status` and make sure previous completed work is committed.
- Do not leave the working tree dirty for long stretches.
- If work is incomplete but stable, create a checkpoint commit with a clear message.
- At the end of each session, all completed work must be committed.

## Commit Message Style

Use clear, specific commit messages in this format:
- `feat: add employee records table and model`
- `feat: build employee list and detail screens`
- `fix: correct leave balance calculation`
- `refactor: move procurement approval logic into service class`
- `chore: seed initial system roles`
