---
spec: <Feature name>
slug: <kebab-slug>
status: draft            # draft | in-review | approved | building | shipped | superseded
version: 0.1
date: <YYYY-MM-DD>
author: <name>
client: <client name>
approver: <who signs it off, by name>
profile: _PROFILE.<project>.md
related: []              # other specs this depends on or is depended upon by
---

<!--
  HOW TO USE THIS FILE

  Eight sections and two appendices. Sections 1-3 are client-readable: no file
  paths, no field handles, no stack vocabulary. Sections 4-8 and the appendices
  are for whoever builds it - a developer or an agent, who are the same reader.

  Three rules hold the document together:

  1. NOTHING IS SAID TWICE. Section 1 is complete on its own. Everything after
     it answers a question section 1 raised. No section re-describes the design.

  2. EVERY CLIENT-VERIFIED CRITERION IN 7 MUST BE PROVABLE BY A SCENARIO IN 7.
     A criterion nobody can prove is either badly written or a real gap. Both
     are worth finding now.

  3. THIS DOCUMENT IS THE IMPLEMENTATION PLAN. There is no second file. Section
     8 carries the build order and the task checklist; Appendix B carries the
     context, the traps and the verification. If you are about to open a new
     plan document, you are about to write the codebase twice - see Appendix B.

  SIZE BUDGET. A feature spec past ~600 lines is a decomposition signal, not a
  thorough spec. Split it and let each half ship on its own.

  Delete these comments as you fill it in.
-->

# <Feature name>

> **Status:** draft - **Version:** 0.1 - **Profile:** `_PROFILE.<project>.md`
> One sentence: what someone can do after this ships that they could not before.

---

## 1. How it works

*Client-readable. Three or four paragraphs.*

Lead with the change in what a person can **do** - "Members can save a filtered search and return to
it later", never "implements a saved-search subsystem". If you cannot write this without naming a
technology, you do not yet understand the feature well enough to spec it.

Then: the approach, and the one idea it rests on. If a real alternative was rejected, say which and
why in a sentence - not a comparison matrix.

**Vocabulary.** *(Optional - include once the feature introduces or overloads three or more terms.)*
Define them in the client's language, not the schema's. Client and developer using one word for two
things is the leading cause of a spec being misread by the person it was written for.

| Term | Means |
|---|---|
| | |

---

## 2. Scope

**In scope**

- ...

**Out of scope** - *each with the reason, so nobody re-proposes it in review*

| Not doing | Why |
|---|---|
| | |

**Later** - *deliberately deferred, not refused*

- ...

---

## 3. User journeys

*Numbered, in the user's vocabulary. Name screens the way a user would - "the search page", not
`market/search/index.twig`.*

**Who this is for**

| Group | What they get |
|---|---|
| | |

List groups who are **affected** as well as those who benefit. Administrators who inherit a new
moderation queue are affected, and that row is often where the hidden work is.

**1. <Journey name>**
   1. ...

**First run - the empty state.** What someone sees before they have any of these. It is the first
thing every new user encounters and the most commonly missing part of a spec. Required, always.

---

## 4. Data model

*Only if the feature stores something. Otherwise write "No new storage." and move on.*

**`<table_or_field_group>`**

| Field | Type | Notes |
|---|---|---|
| | | |

**Why this shape.** One paragraph. Especially: anything derived rather than stored, and why.

**On deletion.** What happens to this data when its owner is deleted.

**States** *(if the thing has a lifecycle)*

| From | Event | To | Side effects |
|---|---|---|---|
| | | | |

---

## 5. Rules

*Numbered `BR-n`, permanently. The build cites these ids; the test plan cites these ids; the task
checklist in section 8 cites these ids. Never renumber - supersede instead.*

Limits, defaults, ordering, validation, permissions, and **anything that gets sent** - every email,
notification and webhook, to whom, when, and whether it can be switched off.

| # | Rule |
|---|---|
| BR-1 | |
| BR-2 | |

**Non-functional** *(optional - performance, security, privacy, accessibility, browser support)*

- ...

---

## 6. Interfaces

*Routes, endpoints and screens. Only what exists at the boundary.*

| Method | Path | Purpose | Auth | Returns |
|---|---|---|---|---|
| | | | | |

**Screens**

| Surface | New or reuse | Notes |
|---|---|---|
| | | |

**Design source.** A supplied design (name it, and say what it shows that scope dropped), or the
developer extending existing patterns. An unanswered design question ships as somebody's guess.

**Copy ownership.** Who writes button labels, empty-state text and error messages. Unowned microcopy
ships as placeholder - if the client owes it, that belongs in Appendix A as blocking.

---

## 7. Test plan

### Client-verified criteria

*Observable behaviour only, numbered `AC-n`. "The marketplace feels faster" cannot be verified;
"a filtered search returns in under 400ms on the reference dataset" can. Every row here must have a
scenario below that proves it.*

| # | Given / When / Then | Proved by |
|---|---|---|
| AC-1 | | TS-1 |

### Test data and preconditions

Named, reusable fixtures - and whether each already exists or has to be made.

### Scenarios

**TS-1 - <name>** - *covers AC-1, AC-3*

| # | Step | Expected |
|---|---|---|
| 1 | | |

*Success criterion: ...*

### Negative and edge cases

*`TN-n`, derived from the failure conditions and from every permission in section 6. Concurrency,
deleted references, limits, third-party unavailable, hostile input.*

| # | Condition | Expected behaviour |
|---|---|---|
| TN-1 | | |

### Automated checks

Which suite each belongs in, what stays manual, and any **test hooks the build must add** - stable
identifiers the tests depend on. Naming them here is what stops them being retrofitted later.

### Regression checks

Named existing journeys this could break, and **why each is at risk**. A list without the "why" gets
skipped.

---

## 8. Build order

*This section replaces the implementation plan. It is the whole plan.*

| Phase | What it delivers | Depends on |
|---|---|---|
| 1 | | - |
| 2 | | 1 |

State the build order explicitly if it differs from the numbering, and say why.

### Tasks

*One line per task. Each names its files, the rules it satisfies, and the check that proves it.
No source code - the code goes in the codebase. Inline a string only where its exact form is
load-bearing and getting it wrong fails silently: a header value, a signature, a regex.*

- [ ] **1.1 <what it delivers>** - `path/to/file`, `path/to/other`
      Rules: BR-1, BR-7 - Verify: B5 #2
- [ ] **1.2 <what it delivers>** - `path/to/file`
      Rules: BR-3 - Verify: B5 #1, TS-1

**Task right-sizing.** A task is the smallest unit worth its own commit and its own review. Fold
setup, config and docs into the task whose deliverable needs them. Split only where a reviewer could
reject one and accept its neighbour.

---

# Appendix A - Open questions

*Numbering is permanent; the build and the commit messages cite these rows.*

Every question raised while writing this spec ends in exactly one of these states. **A guess written
as fact is the only outcome not allowed.**

| # | Question | Owner | State |
|---|---|---|---|
| 1 | | Client | **Open - blocking build** |
| 2 | | <name> | Decided - <the decision, and the reasoning in a clause> |

States: `Open - blocking build` - `Open - blocking release` - `Open - blocking sign-off` -
`Decided` - `Closed` - `Accepted` (a risk taken deliberately, with whose choice it was).

Nothing may remain open at `status: approved` except rows explicitly marked as blocking release.

**Assumptions.** Anything taken as true without confirmation. An assumption recorded here is not a
weakness - it is the mechanism by which someone contradicts you before it is expensive.

- ...

---

# Appendix B - build scaffolding

*For whoever builds this. **This appendix is why there is no separate plan document.** Everything an
implementer needs that is not a rule, a criterion or a task lives here.*

## B1. Before writing anything

Read `_PROFILE.<project>.md`. Everything in it applies - the environment, the verification commands,
and the project's rules about committing and pushing.

## B2. Context to load

*Exact paths, in reading order, each with the reason it matters. Line numbers where they help.
This is the single highest-value section in the document: it is what lets a fresh session start
work without re-deriving the codebase.*

1. `path/to/file.ext` - why, and what to notice in it
2. ...

## B3. Guardrails

*Prohibitions with the consequence attached. A prohibition without its consequence gets rationalised
away at 4pm.*

- **Do not ...** - because ...

## B4. Definition of done

*Derived from sections 5 and 7. Never asked as an interview question - it is computed from what is
already agreed.*

- [ ] Every AC in section 7 passes
- [ ] Every BR in section 5 is enforced, not merely intended
- [ ] The negative cases in section 7 behave as specified
- [ ] The regression checks in section 7 pass
- [ ] B5 below runs clean

## B5. Verification

*Commands, each with the output that means success. An expected output is what turns a command into
an assertion.*

```bash
# 1. <what this proves>
<command>
# expect: <the exact output that means it passed>
```

## B6. Out of bounds

Files, systems and data this work must not touch.

- ...

---

## Change log

| Date | Version | Change | By |
|---|---|---|---|
| <YYYY-MM-DD> | 0.1 | First draft | <name> |
