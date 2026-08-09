<?php

declare(strict_types=1);

namespace Zion\EuWithdrawal\Legal;

final class ROLegalProfile implements LegalProfile
{
    public function id(): string
    {
        return 'RO';
    }

    public function version(): string
    {
        return 'RO-2026.06.19-v1';
    }

    public function country_code(): string
    {
        return 'RO';
    }

    public function standard_period_days(): int
    {
        return 14;
    }

    public function exceptions(): array
    {
        return [
            'EXC-A' => ['ro' => 'Servicii executate integral', 'en' => 'Fully performed services'],
            'EXC-B' => ['ro' => 'Preț dependent de fluctuațiile pieței financiare', 'en' => 'Price dependent on financial market fluctuations'],
            'EXC-C' => ['ro' => 'Produse realizate la comandă sau personalizate clar', 'en' => 'Made-to-order or clearly personalised goods'],
            'EXC-D' => ['ro' => 'Produse susceptibile să se deterioreze sau să expire rapid', 'en' => 'Goods liable to deteriorate or expire rapidly'],
            'EXC-E' => ['ro' => 'Produse sigilate desigilate din motive de sănătate sau igienă', 'en' => 'Sealed goods unsealed for health or hygiene reasons'],
            'EXC-F' => ['ro' => 'Bunuri amestecate inseparabil după livrare', 'en' => 'Goods inseparably mixed after delivery'],
            'EXC-G' => ['ro' => 'Anumite băuturi alcoolice cu livrare ulterioară', 'en' => 'Certain alcoholic beverages with later delivery'],
            'EXC-H' => ['ro' => 'Reparații sau lucrări urgente solicitate expres', 'en' => 'Urgent repairs or maintenance expressly requested'],
            'EXC-I' => ['ro' => 'Înregistrări sau software sigilat desigilat', 'en' => 'Sealed recordings or software unsealed'],
            'EXC-J' => ['ro' => 'Ziare, periodice și reviste, cu excepția abonamentelor', 'en' => 'Newspapers, periodicals and magazines, except subscriptions'],
            'EXC-K' => ['ro' => 'Contracte încheiate în cadrul unei licitații', 'en' => 'Contracts concluded at public auction'],
            'EXC-L' => ['ro' => 'Cazare, transport, închiriere auto, catering sau agrement cu dată determinată', 'en' => 'Accommodation, transport, car rental, catering or leisure with a specific date'],
            'EXC-M' => ['ro' => 'Conținut digital nefurnizat pe suport material după începerea executării', 'en' => 'Digital content not supplied on a tangible medium after performance begins'],
        ];
    }

    public function start_date_rules(): array
    {
        return [
            'service' => 'Data încheierii contractului / Date the contract is concluded',
            'goods' => 'Ziua intrării în posesia fizică a bunurilor / Day of physical possession of the goods',
            'multiple_goods' => 'Ziua intrării în posesia ultimului bun / Day of physical possession of the last good',
            'separate_lots' => 'Ziua intrării în posesia ultimei piese sau a ultimului lot / Day of possession of the last piece or lot',
            'recurring_goods' => 'Ziua intrării în posesia primului bun / Day of possession of the first good',
            'digital' => 'Data încheierii contractului, dacă nu se livrează pe suport material / Contract date for non-tangible digital content',
        ];
    }

    public function mandatory_fields(): array
    {
        return [
            'customer_identity' => 'Identitatea consumatorului / Consumer identity',
            'customer_contact' => 'E-mail sau alt canal de contact / E-mail or another contact channel',
            'order_reference' => 'Identificarea securizată a comenzii / Secure order identification',
            'statement' => 'Declarația neechivocă de retragere / Unambiguous withdrawal statement',
            'submitted_at' => 'Data și ora serverului / Server-side date and time',
            'legal_profile' => 'Profilul juridic și versiunea folosită / Legal profile and version used',
            'confirmation' => 'Confirmare explicită a transmiterii / Explicit submission confirmation',
        ];
    }
}
