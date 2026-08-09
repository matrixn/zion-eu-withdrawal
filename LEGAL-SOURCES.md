# Zion EU Withdrawal — Legal Sources

## Scope

This document defines the legal boundary for Phase 0. The initial implementation is an EU-law foundation with a verified Romanian profile. It is not a legal opinion and it does not claim automatic compliance in every EU Member State.

Profile: `RO-2026.06.19-v1`
Effective reference date: 19 June 2026, subject to legal review before each release.

## Romanian profile

The profile currently models the following principles:

- standard withdrawal period: 14 days, without the consumer having to give a reason, subject to the statutory exceptions;
- services: the period starts on the date the contract is concluded;
- goods: the period starts when the consumer, or a designated third party other than the carrier, obtains physical possession;
- multiple goods or separate lots: the relevant date is possession of the last good, piece or lot;
- recurring deliveries: the relevant date is possession of the first good;
- return shipment after communication of withdrawal: 14 days, unless a more specific rule applies;
- the withdrawal statement must be unambiguous and the server-side timestamp must be retained;
- where the law depends on facts after delivery, the plugin records a potential statutory exception instead of presenting an unverified absolute refusal.

## Exceptions EXC-A → EXC-M

The mapping is deliberately explicit and versioned. It is used as a candidate classification for later eligibility work, not as a replacement for the merchant's legal assessment.

| Code | Romanian profile mapping |
| --- | --- |
| EXC-A | Services fully performed, with the consumer's prior express consent and acknowledgement of loss of the right. |
| EXC-B | Goods or services whose price depends on financial-market fluctuations outside the professional's control. |
| EXC-C | Goods made to the consumer's specifications or clearly personalised. |
| EXC-D | Goods liable to deteriorate or expire rapidly. |
| EXC-E | Sealed goods unsuitable for return for health or hygiene reasons, once unsealed after delivery. |
| EXC-F | Goods which, after delivery, become inseparably mixed with other items. |
| EXC-G | Certain alcoholic beverages with later delivery and market-dependent value. |
| EXC-H | Urgent repair or maintenance work requested expressly by the consumer, within the statutory limits. |
| EXC-I | Sealed audio/video recordings or sealed computer software that has been unsealed. |
| EXC-J | Newspapers, periodicals and magazines, except subscription contracts. |
| EXC-K | Contracts concluded at a public auction. |
| EXC-L | Accommodation for non-residential purposes, goods transport, car rental, catering or leisure services with a specific date or period. |
| EXC-M | Digital content not supplied on a tangible medium after performance begins, with express consent, acknowledgement and the required confirmation. |

## Mandatory submission evidence

The future withdrawal flow must retain, at minimum:

- consumer identity and contact channel;
- securely identified order or contract reference;
- unambiguous withdrawal statement;
- server-side date and time;
- legal profile and version used;
- source of the request (account, guest secure link or public flow);
- confirmation action and immutable content snapshot;
- item-level eligibility and potential exception code when applicable.

## Sources

1. [OUG nr. 34/2014 — Portal Legislativ](https://legislatie.just.ro/Public/DetaliiDocument/158913), especially art. 9, 11, 13, 14 and 16.
2. [Directive 2011/83/EU — EUR-Lex](https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=CELEX%3A32011L0083), especially arts. 9–16.
3. [Directive (EU) 2023/2673 — EUR-Lex, Romanian text](https://eur-lex.europa.eu/legal-content/RO/TXT/?uri=celex%3A32023L2673), including the online withdrawal-function requirements in art. 11a.
4. [OUG nr. 18/2026 — Portal Legislativ](https://legislatie.just.ro/Public/DetaliiDocument/308474), including the clear confirmation-function wording.

## Eligibility engine boundary

Phase 7 uses product/category metadata and an explicit delivery-date provider to create an indicative item-level signal: `standard`, `potential_exception` or `unknown`. The signal is recorded in the withdrawal evidence and never becomes an automatic rejection, approval or refund decision. If delivery information is unavailable, the submission remains available and the merchant is shown that manual review is needed.

## Release rule

Any change to the profile, exceptions, start-date rules or required evidence must update this file, `CHANGELOG.md`, the profile version and the relevant tests before release. Legal sources should be re-checked before every public release.
