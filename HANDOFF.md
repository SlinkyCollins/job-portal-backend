MISSION: BUILDING — CURRENT CHECKPOINT

Goal:
Build a migration safety net for JobNet before PHP → Laravel
and Angular 18 → Angular 22 migration.

Testing principle:
"If a test doesn't materially protect a risky behavior
we're about to change, we probably don't need it."

Momentum rule:
If a test becomes a rabbit hole, defer it and continue.

Completed:
1. Native signup ✅
2. Native login ✅

Deferred:
3. Firebase/Google login ⏸️
4. Firebase/Facebook login ⏸️

Current candidate:
5. Social account linking

Decision rule:
Determine whether #5 can be tested without requiring live
Firebase provider authentication. If yes, implement it.
If no, defer it and move to #6.

Do not expand testing scope unnecessarily.
Do not turn this into a testing-specialist project.