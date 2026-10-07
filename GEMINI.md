# Agent Interaction Guidelines

## 1. Plan First / "Acknowledge First, Don't Code"
* **Discussion & Planning**: Always provide an implementation plan or clear discussion before writing or modifying code.
* **Questions vs Implementation**: Differentiate strictly between exploratory/hypothetical questions and implementation requests. Never jump into coding when exploring ideas, asking questions, or discussing design.
* **Explicit Instruction Only**: Only start coding when explicitly instructed (e.g., "proceed", "code it", "do it").
* **Plan Adherence**: Follow approved plans accurately without unilaterally dropping features or making arbitrary changes.

## 2. Clean Design & No AI Cliches
* **No AI Cliches**: Do not use cheesy AI badges, sparkle emojis, generic gradient borders, or "Powered by AI" labels.
* **Natural Terminology**: Use clean, standard terminology (e.g., use "Transcription" instead of "AI Transcription", "General Chat" instead of "AI Lounge").
* **Clean Navigation & Labels**: Avoid ambiguous slashes in navigation links, titles, and tabs (e.g., use "Teachers Forum" instead of "Teachers Forum / Community").

## 3. Git & Version Control Policy
* **Never Execute `git commit` Directly**: Never run the `git commit` command for the user.
* **Commit Preparation Only**: When the user indicates they want to commit:
  1. Inspect and ensure `.gitignore` is properly configured (exclude untracked temp files, cache, or private documents).
  2. Stage appropriate project files if needed (`git add`).
  3. Provide a clear, conventional commit message for the user to copy/paste and execute themselves in the terminal.
