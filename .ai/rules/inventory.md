---
paths:
  - 'app/{Models,Services,Http/Controllers/Inventory}/**/*Accountability*.php'
---

# Inventory

## PAR and ICS accountability documents
Assigned durable property must have a versioned accountability document. Use PAR when acquisition cost is at least PHP 50,000 and ICS below that threshold. Freeze asset and recipient details on issuance; transfers, renewals, returns, cancellations, and acknowledgments must append an actor/attestation history rather than rewriting prior documents.

## New PPE uses ARE with personal acknowledgment and division notation
This supersedes earlier PAR-for-new-PPE rules: new PPE accountability is ARE; preserve historical PAR and ICS evidence. Supply officer issuance freezes recipient department/division IDs. The named end user personally acknowledges, then an independent eligible temporary division head in that frozen jurisdiction notes; no witnessed ARE acknowledgment or manager bypass. No division means notation optional and noter fields nullable. PPE AREs retain participant access, and Noted AREs filters strictly to the current user's own notation, including empty results. IAR is not required for this flow.
