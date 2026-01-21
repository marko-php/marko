Review .claude/architecture.md and the following chart (in order) and determine what to work on next. Note that all other plans not listed below at .claude/plans/**/_plan.md have already been completed, and some of the below may have also been already completed too. Note that a pending status means that the plan has not yet been implemented.

  ┌───────┬─────────────────┬────────────────────────────────────────────────────────────┐                                                 
  │ Order │     Package     │                         Rationale                          │                                                 
  ├───────┼─────────────────┼────────────────────────────────────────────────────────────┤                                                 
  │ 1     │ config          │ Foundation - almost every package loads config             │                                                 
  ├───────┼─────────────────┼────────────────────────────────────────────────────────────┤                                                 
  │ 2     │ hashing         │ Standalone, simple - auth depends on it                    │                                                 
  ├───────┼─────────────────┼────────────────────────────────────────────────────────────┤                                                 
  │ 3     │ log             │ Useful for debugging as we build other packages            │                                                 
  ├───────┼─────────────────┼────────────────────────────────────────────────────────────┤                                                 
  │ 4     │ cache           │ Core infrastructure, many packages benefit                 │                                                 
  ├───────┼─────────────────┼────────────────────────────────────────────────────────────┤                                                 
  │ 5     │ filesystem      │ File operations used by cache-file, log-file, session-file │                                                 
  ├───────┼─────────────────┼────────────────────────────────────────────────────────────┤                                                 
  │ 6     │ session         │ State management - auth's SessionGuard requires it         │                                                 
  ├───────┼─────────────────┼────────────────────────────────────────────────────────────┤                                                 
  │ 7     │ validation      │ Request validation for APIs/forms                          │                                                 
  ├───────┼─────────────────┼────────────────────────────────────────────────────────────┤                                                 
  │ 8     │ view            │ Template rendering                                         │                                                 
  ├───────┼─────────────────┼────────────────────────────────────────────────────────────┤                                                 
  │ 9     │ mail            │ Email sending (can optionally use view for templates)      │                                                 
  ├───────┼─────────────────┼────────────────────────────────────────────────────────────┤                                                 
  │ 10    │ auth            │ Authentication - depends on session + hashing              │                                                 
  ├───────┼─────────────────┼────────────────────────────────────────────────────────────┤                                                 
  │ 11    │ queue           │ Background jobs - depends on database driver               │                                                 
  ├───────┼─────────────────┼────────────────────────────────────────────────────────────┤                                                 
  │ 12    │ errors-advanced │ Enhancement layer - not critical path                      │                                                 
  ├───────┼─────────────────┼────────────────────────────────────────────────────────────┤                                                 
  │ 13    │ framework       │ Metapackage - must be last                                 │                                                 
  └───────┴─────────────────┴────────────────────────────────────────────────────────────┘
