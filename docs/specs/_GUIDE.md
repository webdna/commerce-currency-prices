# How to write a spec

`_TEMPLATE.md` is eight sections and two appendices. This guide is how to fill it in without it
turning into either marketing copy or a wall of implementation detail.

---

## The idea in one paragraph

A spec fails when it serves one audience at the expense of the others. Write it for the client alone
and the developer re-invents half the decisions. Write it for the developer alone and the client
signs off on something they did not understand. Write it for neither and an AI agent fills the gaps
with plausible guesses.

So the document is **layered, not blended**. Sections 1-3 are complete and readable on their own - a
client can read them, understand exactly what they are buying, and stop. Sections 4-8 answer the
questions those raise for someone who has to build it. The appendices carry what is true but not
worth a client's attention: what is still undecided, and the scaffolding a build needs.

Nothing is said twice. That is the property that keeps the layers from drifting apart.

---

## Three rules that make it work

### 1. Every criterion in section 7 must be provable by a scenario in section 7

The client-verified criteria say what was agreed. The scenarios say how each is proved. Follow that
chain in either direction and there is no gap between what a client signed and what someone checked.

This single constraint disciplines both ends of the document:

- It stops section 1 becoming aspirational. "The marketplace feels faster" cannot be verified, so it
  cannot be a criterion. "A filtered search returns results in under 400ms on the reference dataset"
  can.
- It stops the build inventing its own finish line. An agent told only "build saved searches" will
  decide for itself when it is done. An agent told "AC-4 requires that a saved search survives a
  vertical being renamed, proved by TS-6" cannot.

If you write a criterion you cannot verify, you have either written it badly or discovered a real
gap in the design. Both are worth finding now.

### 2. Nothing is silently guessed

The other half of a spec being *understood* is that nobody has to wonder which parts were agreed and
which were filled in. Every question raised while writing a spec ends in exactly one of four states,
and the document must show which:

| State | Where it goes |
|---|---|
| Answered | The relevant section |
| Someone must decide it | **Appendix A**, as an open question with an owner |
| The client must supply it | **Appendix A**, with an owner, marked blocking |
| Taken as true without confirmation | **Appendix A**, under Assumptions |

A guess written as fact is the only outcome that is not allowed. An assumption is not a weakness in
the spec - it is the mechanism by which someone contradicts you before it is expensive.

Appendix A numbering is **permanent**. The build cites those rows, and so do commit messages. A
question that gets answered changes state; it never disappears and its number is never reused.

### 3. The spec is the implementation plan

**There is no separate plan document.** Section 8 carries the build order and the task checklist.
Appendix B carries the preconditions, the context to load, the guardrails, the definition of done
and the verification commands. Between them that is everything a plan ever held that was worth
holding.

This rule exists because the alternative was measured. A separate plan document, written to the
convention that every task must show its code, ran to 4,600 lines of which 63% was source code -
code written *before* contact with the codebase, so some of it was wrong, which then needed
"as built" correction commits back into the plan. The same work was written three times: plan, then
code, then plan again.

The convention that produces that is worth naming, because it is not unreasonable in its own terms:
it assumes the implementer is **a fresh agent per task with no context**, so nothing can be
cross-referenced and everything must be shown. If you genuinely dispatch one context-free subagent
per task, that convention is right and you should follow it.

If you do not - if a human or a single agent builds the feature in sequence with the spec open -
then it is paying a large cost for a delivery model you are not using. Write the task checklist
instead.

**What a task line carries:** what it delivers, its files, the `BR-` rules it satisfies, and the
check that proves it. **What it does not carry:** source code. Inline a string only where its exact
form is load-bearing and getting it wrong fails silently - a header value, a function signature, a
regex. Everything else belongs in the codebase, once.

---

## When to write a full spec at all

**Write the full spec in the week the feature is built, not months ahead.**

A batch of specs written in advance ages. It ages in three ways, all of which have been observed:
the format changes underneath it and every file needs re-cutting; the codebase moves and the
context-to-load section goes stale; and the feature gets designed properly only when someone starts
building it, so the spec is revised heavily during the build anyway. Writing thirteen specs across
eleven days, none of which shipped in that time, buys less certainty than it looks like it does.

The commercial pressure to spec everything up front is real - a statement of work needs coverage,
and a client needs to know what they are paying for. That is a **different document**. Write a
one-to-two page **scope note** per feature: what it is, who it is for, what is in and out, and the
open questions that block a price. Enough to sign and to cost. The full spec follows, per feature,
when that feature comes up.

| | Scope note | Full spec |
|---|---|---|
| Written | Up front, for all features | The week the feature is built |
| Length | 1-2 pages | Under 600 lines |
| Serves | Sign-off, pricing, scope agreement | The build |
| Contains | Sections 1, 2 and blocking questions | Everything |

---

## The size budget

**A feature spec past ~600 lines is a decomposition signal, not a thorough spec.**

Length in a spec usually means one of three things, and only one of them is good: the feature is
genuinely large (split it), the spec is restating itself across sections (the "nothing is said
twice" rule is being broken), or it has absorbed implementation detail that belongs in the code.

Check length against the split first. Two 400-line specs that each ship on their own beat one
800-line spec that ships in one lump.

---

## The profile

Every spec's front matter points at a **project profile** - `_PROFILE.<project>.md` in the same
folder. It carries the half of a build contract that is true of the whole project rather than of one
feature: the stack, the commands, where code belongs, the commands that verify a build, and the
project's specific landmines.

That split is what makes the template portable. To use it on another project, copy `_TEMPLATE.md`
and `_GUIDE.md` across and write a new profile; nothing in either file assumes a particular stack.

The profile is never copied from another project - it is the one file whose content is specific to a
codebase. Do not invent its traps section either: an unpopulated one is honest, an imagined one gets
handed to a build agent as fact.

---

## The four everyone forgets

These are the most common causes of a spec that all parties signed and none of them agreed on.
Answer all four, every time, even for a small feature:

1. **The empty state.** What someone sees before they have any of these. It is the first thing every
   new user encounters and it is missing from most specs. Goes in section 3, with the surface in 6.
2. **Notifications.** Whether anything is sent, to whom, when, and whether it can be switched off.
   Goes in section 5 - and if it is a member-visible email, in section 3 too.
3. **Copy ownership.** Who writes button labels, empty-state text, and error messages. Unowned
   microcopy ships as placeholder. Goes in section 6, and in Appendix A as blocking if the client
   owes it.
4. **Design source.** A supplied design, or the developer extending existing patterns. Goes in
   section 6. An unanswered design question ships as somebody's guess.

---

## Section by section

**There is deliberately no "problem" section.** A spec that explains a client's own business back to
them reads as padding, and it is the section most likely to be written from assumption. Understand
the problem thoroughly - it decides what belongs in scope and whether the criteria are the right
ones - then let it show in a sharper section 1 and a better-judged section 2, not in prose.

### 1. How it works

Lead with the change in what someone can *do*. "Members can save a filtered search and return to it
later" - not "implements a saved-search subsystem". If you cannot write this without naming a
technology, you do not yet understand the feature well enough to spec it.

Then the approach and the one idea it rests on. A rejected alternative gets a sentence, not a
comparison matrix - record it so it is not re-proposed, not so it is re-litigated.

**Vocabulary** is optional, and worth it as soon as the feature introduces or overloads three or
more terms. Client and developer using one word for two different things is a leading cause of a
spec being misread by exactly the person it was written for. Define terms in the client's language,
not the schema's.

### 2. Scope

Out of scope needs **the reason attached to each row**. Without it, every review re-proposes the
same three things. "Later" is a real category and worth keeping distinct from "no" - it tells a
client the idea was heard.

### 3. User journeys

Numbered, in the user's vocabulary. Name screens the way the user would - "the search page", not a
template path. If a journey needs more than about eight steps it is probably two journeys.

List groups who are *affected* as well as groups who *benefit*. Administrators who inherit a new
moderation queue are affected. That row is often where the hidden work is.

### 4. Data model

Skip it honestly if there is no new storage - "No new storage." is a complete section. When there is
storage, the paragraph that earns its place is **why this shape**: especially anything derived
rather than stored, and why, because that is the decision a later reader is most likely to reverse
by accident.

### 5. Rules

`BR-n`, numbered permanently. This is the section the build, the tests and the task checklist all
cite, so the numbers are an interface. Never renumber - supersede.

The commonly missed half of this section is **anything that gets sent**. Every email, notification
and webhook: to whom, when, and whether it can be switched off.

### 6. Interfaces

Only what exists at the boundary. The auth column is not optional - an endpoint whose auth is
unstated gets built open.

### 7. Test plan

The criteria are what the client signs; the scenarios are how each is proved; the negative cases
come from the failure conditions and from **every permission in section 6**. Regression checks need
the *why is this at risk* clause or they get skipped.

Name any **test hooks the build must add** here. A stable identifier that the tests depend on is
cheap to add while the code is being written and expensive to retrofit.

### 8. Build order

Phases with dependencies, then the task checklist. Say the build order explicitly when it differs
from the numbering, and give the reason - "the most dangerous phase goes last" is a real reason and
a later reader needs it.

**Section 8 is internal.** Since the spec became the implementation plan, its task lines name modules
and file paths, which is build detail a client neither needs nor benefits from. That creates an
awkwardness worth knowing about: section 8 sits *before* Appendix A, and Appendix A - the open
questions - is client-facing, because it is the list you walk through with them. So in authored order
the internal material is sandwiched between two client-facing parts.

`/spec publish` resolves this rather than the author working around it. The full document, which is
the default, **moves section 8 down beside Appendix B under a divider heading**, so everything
internal is contiguous and a client copy is made by deleting from the divider to the end. Headings
are never renumbered when this happens - the task lines cite `B5`, and the spec refers to B1-B6
throughout.

**So write section 8 where it belongs and do not try to pre-arrange it for a client.** Publishing
handles that.

### Appendix A

Open questions with owners and states. Nothing may remain open at `status: approved` except rows
explicitly marked as blocking release.

### Appendix B

The build scaffolding. **B2, context to load, is the single highest-value section in the document** -
exact paths in reading order, each with the reason it matters and line numbers where they help. It
is what lets a fresh session start work without re-deriving the codebase, and it is the part a
separate plan document was mostly duplicating.

B3 guardrails need **the consequence attached to each prohibition**. "Do not compare the token with
`===`" is ignorable. "Do not compare the token with `===` - a non-constant-time comparison leaks it
a byte at a time" is not.

B5 verification: every command gets the output that means success. An expected output is what turns
a command into an assertion.
