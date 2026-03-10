# Contributing

Thank you for taking the time to contribute.

This project is designed as a **local web application**, not a hosted service. The goal is to provide simple, portable media tools that run entirely on the user's machine through a browser interface.

Because of this design, a few guidelines must be followed when contributing.

---

## Project Philosophy

This application is intentionally built to run **locally** using PHP and browser-based interfaces.  
It is not intended to rely on external servers, cloud services, or remote APIs.

Local execution is a deliberate design choice, not a limitation.

All features should continue to support this approach.

---

## Feature Contributions

Contributors are welcome to:

- Add new tools or services to the application
- Improve existing implementations
- Optimize performance or reliability
- Expand platform compatibility

However:

- **Existing features must not be removed.**
- Changes that break current functionality should be avoided.
- Improvements should remain compatible with the current UI and workflow.

---

## Portability Requirements

New features should be designed to be **fully portable**.

The application should remain easy to run on any machine without complex installation steps.

When adding functionality:

- Use the existing `bin` directory for external tools
- Avoid introducing additional dependency folders
- Avoid requiring global system installations when possible

Adding large or complex directory structures can make the project harder to maintain and distribute.

---

## Platform Support

The project currently focuses on **Windows environments**, mainly due to the original development environment.

Support for **Linux or macOS** is welcome and encouraged.

If adding cross-platform support:

- Detect the operating system when necessary
- Ensure commands and paths remain compatible
- Prefer solutions that work across multiple platforms

---

## Code Structure

When contributing:

- Keep implementations simple and readable
- Avoid unnecessary abstraction or frameworks
- Maintain compatibility with the current file structure
- Ensure new features integrate cleanly with the existing interface

---

## Submitting Changes

1. Fork the repository
2. Create a new branch
3. Implement your changes
4. Submit a Pull Request with a clear explanation of the improvement

All contributions are reviewed before merging.

---

## Final Note

This project is intended to remain a lightweight, local utility. Contributions should focus on improving functionality while preserving simplicity and portability.
