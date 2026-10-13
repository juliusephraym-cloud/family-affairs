# What the source material says — and what still needs confirmation

Sources reviewed (kept **outside** this repository; a full, name-bearing version of this document is kept privately by the administrator):

- **[C]** WhatsApp export "Family affairs group" — `_chat.txt`, 5,206 lines, 17 Oct 2025 → 13 Oct 2026, plus attachments.
- **[M]** Minutes "KIKAO CHA FAMILY AFFAIRS 27/7/2026" (two identical copies of the .docx in the export).
- **[S]** Spreadsheet "FAMILY AFFAIRS-LATEST UPDATE-MAY 2026 3.xlsx" (sheets 2021–2026, MATUMIZI), shared in the chat on 23 Jun 2026.

No names, phone numbers, account numbers or amounts from these sources are hard-coded in the application. Rules found in them are seeded as **unconfirmed** policy rules (see `database/seeders/PolicyRulesSeeder.php`), each with its source quoted, and the app will not act on them until leadership confirms them in **Katiba na Kanuni**.

## 1. Requirements the sources confirm

| Area | Evidence |
|---|---|
| Leadership roles | [M] attendance list: Mwenyekiti Msaidizi, Katibu, Katibu Msaidizi, Mhazini, Mhazini Msaidizi. [C] the Chairperson signs announcements as *Mwenyekiti*. |
| Monthly contributions | [S] 10,000/month (2021 – Jun 2024), 15,000 (Jul 2024 – Jul 2025), 20,000 (from Aug 2025); entry fee 20,000 in 2021. |
| Welfare ("jamii") fund | [S] "PESA YA JAMII FROM JULY 2024" — per-member totals that match 5,000/month then 10,000/month of the contribution (inference, not stated). [C] "mfuko wa jamii ulikuwa na 700k" (Jun 2026). |
| Joint account | [C] Since May 2026 contributions go to a CRDB joint account with two signatories; members post the slip. (Account details deliberately **not** stored in code — the Treasurer enters payment instructions in Admin → Settings.) |
| Bereavement rule | [C] Secretary quoting the constitution (31 Jan 2026 and 26 Jul 2026): fund pays 200,000; each member 30,000 within 7 days; for father, mother, own child, wife or husband; one payout per deceased. Applied in both 2026 cases. |
| Arrears / exit | [M] Clause 6:02 amended: 3 consecutive unpaid months = member chose to leave. |
| Online meetings | [M] Clause 6:03: two online meetings, September and December. [C] One was held by WhatsApp call on 11 Oct 2026. |
| Penalties | [M] penalties start "July"; payments clear penalties first. [C] meeting-absence fine 10,000 announced by the Secretary (11 Oct 2026). |
| Investments | [M] UTT account opened; all money to UTT except 1,000,000 emergency reserve + welfare fund. Shares proposal assigned for research (not a decision). [S] UTT 5,000,000 in the expenses sheet. |
| Charity (sadaka) | [M] offering collected; [C] destination chosen by poll (15 votes for paying cancer patients' bills vs 1 for prisons), letter to the hospital, visit 21 Aug 2026, total reported 650,000. |
| Secreto | [M] three-year tradition; gift from 100,000, physical or voucher, bring it or send a proxy. [C] names drawn on paper slips; several members lost their slips — the app solves this. Children asked for their own Secreto. |
| Events & committees | [C] Dec 2025 children's blessing: committees for food, decorations, drinks, fresh juice, MC, music, cake, jumping castle, prayers, reception, children, photo & video; venues; vendor numbers; menu; budget 1.29 M (2025) and 1.56 M (2026) with per-person purchase lines. |
| Easter gathering | [C] long debate on venue/day, three polls, then cancelled by the Chair; "kikatiba get together yetu iwe siku ya Pasaka" was asserted but not documented. Next meeting "Pasaka 2027" [M]. |
| Annual photo | [C] Chair: "Ni rasmi sasa kutakuwa na picha ya pamoja kila kikao cha mwezi wa 6". |
| Practices (not rules) | [S] newborn gift 150,000; wedding/send-off 200,000; one 30 % refund on exit. |
| The platform itself | [C] 11 Oct 2026: a member offered to build the system; hosting ≈ 300,000 TZS/year; camera stand and microphone requested for recording meetings and children's interviews. |

## 2. Ambiguities that need an authorised answer

1. **Minutes date.** The document is titled 27/**7**/2026, but the gathering, Secreto and meeting happened on **27 June 2026** (poll result 3 Jun; chat 27 Jun). A member pointed out the error on 16 Jul 2026. → Correct the date when importing (`php artisan fa:import-minutes … --date=2026-06-27`).
2. **Blank totals in the minutes.** Amount collected, amount spent, amount placed in UTT and the sadaka amount are all "……". → Enter opening balances from bank/UTT statements, not from the minutes.
3. **Penalty amount.** Minutes say penalties start in July, but no amount is written anywhere. → `penalty.late_monthly_amount` is seeded with **no value**; penalties cannot be applied until set and confirmed.
4. **Due date.** Never written; chat implies the previous month was due by the 5th ("baada ya saa6 usiku leo una fine", 5 Jul). → `contribution.due_day` has no value.
5. **Welfare split** of the monthly contribution is inferred from spreadsheet totals only.
6. **Arrears figures disagree.** The minutes list 7 members with amounts owed; the spreadsheet's DENI JAMII column and monthly sheets give different figures for several of them. → Import the spreadsheet for review and let the Treasurer settle a cut-over balance per member.
7. **Spelling / identity conflicts.** About ten members appear under two or three spellings across the spreadsheet years, the minutes and the chat (maiden vs married surnames, nicknames, typos). The import review resolves these one by one.
8. **Who is a member.** The chat contains spouses, in-laws and children who are not on the contribution sheet; one person left with a refund; a new member joined in May 2026. → Nobody is imported automatically; the Secretary decides per person.
9. **Is Easter attendance constitutional?** Asserted in the chat, not documented.
10. **Secreto rules** were recorded as *proposals* ("mapendekezo") at the meeting.
11. **Constitution text** was never shared in the export. → The Constitution page stays empty until the Secretary enters the approved text.
12. **Joint-account signatories.** The account name appears with two different signatory spellings in the chat.

## 3. How the import workflow handles real data

- `Admin → Uingizaji wa orodha`: upload the Treasurer's .xlsx (or a CSV). Every name is staged with its per-sheet figures; for each the Secretary **links** it to an existing person, **creates** a non-member record, or **rejects** it. Nothing becomes a member, gets a phone number, or creates money records automatically.
- Historical arrears are entered by the Treasurer as one-off obligations flagged *ya zamani* (historical) after confirming them.
- `php artisan fa:import-minutes <file.docx> --date=YYYY-MM-DD` creates a **draft** meeting whose minutes say "INAHITAJI UKAGUZI"; it must be edited and approved through the normal workflow.
