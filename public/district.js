(function (root, factory) {

    const api = factory();

    if (
        typeof module === 'object' &&
        module.exports
    ) {
        module.exports = api;
    } else {
        root.FederalDistrict = api;
    }

})(
    typeof globalThis !== 'undefined'
        ? globalThis
        : this,

    function () {

        const DISTRICT_NAMES = {
            central: 'Центральный',
            northwest: 'Северо-Западный',
            south: 'Южный',
            northCaucasus: 'Северо-Кавказский',
            volga: 'Приволжский',
            ural: 'Уральский',
            siberian: 'Сибирский',
            farEast: 'Дальневосточный'
        };


        const REGION_EXACT = {

            // ЦФО
            'белгородская': 'central',
            'брянская': 'central',
            'владимирская': 'central',
            'воронежская': 'central',
            'ивановская': 'central',
            'калужская': 'central',
            'костромская': 'central',
            'курская': 'central',
            'липецкая': 'central',
            'московская': 'central',
            'москва': 'central',
            'орловская': 'central',
            'рязанская': 'central',
            'смоленская': 'central',
            'тамбовская': 'central',
            'тверская': 'central',
            'тульская': 'central',
            'ярославская': 'central',

            // СЗФО
            'карелия': 'northwest',
            'коми': 'northwest',
            'архангельская': 'northwest',
            'ненецкий': 'northwest',
            'вологодская': 'northwest',
            'калининградская': 'northwest',
            'ленинградская': 'northwest',
            'мурманская': 'northwest',
            'новгородская': 'northwest',
            'псковская': 'northwest',
            'санкт-петербург': 'northwest',
            'петербург': 'northwest',
            'спб': 'northwest',

            // ЮФО
            'адыгея': 'south',
            'калмыкия': 'south',
            'крым': 'south',
            'краснодарский': 'south',
            'астраханская': 'south',
            'волгоградская': 'south',
            'ростовская': 'south',
            'севастополь': 'south',

            // СКФО
            'дагестан': 'northCaucasus',
            'ингушетия': 'northCaucasus',
            'кабардино-балкарская': 'northCaucasus',
            'карачаево-черкесская': 'northCaucasus',
            'северная осетия': 'northCaucasus',
            'северная осетия алания': 'northCaucasus',
            'алания': 'northCaucasus',
            'чеченская': 'northCaucasus',
            'ставропольский': 'northCaucasus',

            // ПФО
            'башкортостан': 'volga',
            'башкирия': 'volga',
            'марий эл': 'volga',
            'мордовия': 'volga',
            'татарстан': 'volga',
            'удмуртская': 'volga',
            'чувашская': 'volga',
            'пермский': 'volga',
            'кировская': 'volga',
            'нижегородская': 'volga',
            'оренбургская': 'volga',
            'пензенская': 'volga',
            'самарская': 'volga',
            'саратовская': 'volga',
            'ульяновская': 'volga',

            // УрФО
            'курганская': 'ural',
            'свердловская': 'ural',
            'тюменская': 'ural',
            'ханты-мансийский': 'ural',
            'ханты-мансийский югра': 'ural',
            'югра': 'ural',
            'ямало-ненецкий': 'ural',
            'челябинская': 'ural',

            // СФО
            'алтайский': 'siberian',
            'алтай': 'siberian',
            'тыва': 'siberian',
            'тува': 'siberian',
            'хакасия': 'siberian',
            'красноярский': 'siberian',
            'иркутская': 'siberian',
            'кемеровская': 'siberian',
            'кузбасс': 'siberian',
            'новосибирская': 'siberian',
            'омская': 'siberian',
            'томская': 'siberian',

            // ДФО
            'бурятия': 'farEast',
            'саха': 'farEast',
            'якутия': 'farEast',
            'забайкальский': 'farEast',
            'камчатский': 'farEast',
            'приморский': 'farEast',
            'хабаровский': 'farEast',
            'амурская': 'farEast',
            'магаданская': 'farEast',
            'сахалинская': 'farEast',
            'еврейская': 'farEast',
            'чукотский': 'farEast'
        };


        const REGION_STEMS = {

            // ЦФО
            'белгород': 'central',
            'брянск': 'central',
            'владимир': 'central',
            'воронеж': 'central',
            'иванов': 'central',
            'калуж': 'central',
            'костром': 'central',
            'курск': 'central',
            'липецк': 'central',
            'московск': 'central',
            'орлов': 'central',
            'рязан': 'central',
            'смоленск': 'central',
            'тамбов': 'central',
            'твер': 'central',
            'тульск': 'central',
            'ярослав': 'central',

            // СЗФО
            'карел': 'northwest',
            'коми': 'northwest',
            'архангел': 'northwest',
            'ненец': 'northwest',
            'вологод': 'northwest',
            'калининград': 'northwest',
            'ленинград': 'northwest',
            'мурман': 'northwest',
            'новгород': 'northwest',
            'псков': 'northwest',
            'петербург': 'northwest',

            // ЮФО
            'адыге': 'south',
            'калмык': 'south',
            'крым': 'south',
            'краснодар': 'south',
            'астрахан': 'south',
            'волгоград': 'south',
            'ростов': 'south',
            'севастопол': 'south',

            // СКФО
            'дагестан': 'northCaucasus',
            'ингуш': 'northCaucasus',
            'кабардино': 'northCaucasus',
            'балкар': 'northCaucasus',
            'карачаево': 'northCaucasus',
            'черкес': 'northCaucasus',
            'осет': 'northCaucasus',
            'алания': 'northCaucasus',
            'чечен': 'northCaucasus',
            'ставропол': 'northCaucasus',

            // ПФО
            'башкортостан': 'volga',
            'башкир': 'volga',
            'марий': 'volga',
            'мордов': 'volga',
            'татарстан': 'volga',
            'удмурт': 'volga',
            'чуваш': 'volga',
            'перм': 'volga',
            'киров': 'volga',
            'нижегород': 'volga',
            'оренбург': 'volga',
            'пенз': 'volga',
            'самар': 'volga',
            'саратов': 'volga',
            'ульянов': 'volga',

            // УрФО
            'курган': 'ural',
            'свердлов': 'ural',
            'тюмен': 'ural',
            'ханты': 'ural',
            'югра': 'ural',
            'ямало': 'ural',
            'челябин': 'ural',

            // СФО
            'алтай': 'siberian',
            'тыва': 'siberian',
            'тува': 'siberian',
            'хакас': 'siberian',
            'краснояр': 'siberian',
            'иркут': 'siberian',
            'кемеров': 'siberian',
            'кузбасс': 'siberian',
            'новосибир': 'siberian',
            'омск': 'siberian',
            'томск': 'siberian',

            // ДФО
            'бурят': 'farEast',
            'саха': 'farEast',
            'якут': 'farEast',
            'забайкал': 'farEast',
            'камчат': 'farEast',
            'примор': 'farEast',
            'хабаров': 'farEast',
            'амур': 'farEast',
            'магадан': 'farEast',
            'сахалин': 'farEast',
            'еврей': 'farEast',
            'чукот': 'farEast'
        };


        const CITY_MAP = {

            // ЦФО
            'москва': 'central',
            'балашиха': 'central',
            'химки': 'central',
            'мытищи': 'central',
            'подольск': 'central',
            'королев': 'central',
            'люберцы': 'central',
            'красногорск': 'central',
            'одинцово': 'central',
            'воронеж': 'central',
            'липецк': 'central',
            'тамбов': 'central',
            'белгород': 'central',
            'курск': 'central',
            'орел': 'central',
            'тула': 'central',
            'рязань': 'central',
            'калуга': 'central',
            'брянск': 'central',
            'смоленск': 'central',
            'тверь': 'central',
            'ярославль': 'central',
            'владимир': 'central',
            'иваново': 'central',
            'кострома': 'central',

            // СЗФО
            'санкт-петербург': 'northwest',
            'петербург': 'northwest',
            'калининград': 'northwest',
            'мурманск': 'northwest',
            'архангельск': 'northwest',
            'северодвинск': 'northwest',
            'вологда': 'northwest',
            'череповец': 'northwest',
            'псков': 'northwest',
            'великий новгород': 'northwest',
            'петрозаводск': 'northwest',
            'сыктывкар': 'northwest',

            // ЮФО
            'краснодар': 'south',
            'сочи': 'south',
            'новороссийск': 'south',
            'анапа': 'south',
            'геленджик': 'south',
            'ростов-на-дону': 'south',
            'ростов на дону': 'south',
            'таганрог': 'south',
            'шахты': 'south',
            'волгоград': 'south',
            'волжский': 'south',
            'астрахань': 'south',
            'элиста': 'south',
            'майкоп': 'south',
            'симферополь': 'south',
            'севастополь': 'south',
            'ялта': 'south',

            // СКФО
            'махачкала': 'northCaucasus',
            'каспийск': 'northCaucasus',
            'дербент': 'northCaucasus',
            'грозный': 'northCaucasus',
            'ставрополь': 'northCaucasus',
            'пятигорск': 'northCaucasus',
            'кисловодск': 'northCaucasus',
            'ессентуки': 'northCaucasus',
            'невинномысск': 'northCaucasus',
            'нальчик': 'northCaucasus',
            'владикавказ': 'northCaucasus',
            'назрань': 'northCaucasus',
            'черкесск': 'northCaucasus',

            // ПФО
            'нижний новгород': 'volga',
            'дзержинск': 'volga',
            'богородск': 'volga',
            'шаранга': 'volga',
            'казань': 'volga',
            'набережные челны': 'volga',
            'альметьевск': 'volga',
            'нижнекамск': 'volga',
            'йошкар-ола': 'volga',
            'чебоксары': 'volga',
            'саранск': 'volga',
            'пенза': 'volga',
            'кузнецк': 'volga',
            'самара': 'volga',
            'тольятти': 'volga',
            'сызрань': 'volga',
            'саратов': 'volga',
            'энгельс': 'volga',
            'балаково': 'volga',
            'балашов': 'volga',
            'ульяновск': 'volga',
            'димитровград': 'volga',
            'киров': 'volga',
            'пермь': 'volga',
            'березники': 'volga',
            'соликамск': 'volga',
            'уфа': 'volga',
            'стерлитамак': 'volga',
            'мелеуз': 'volga',
            'салават': 'volga',
            'нефтекамск': 'volga',
            'оренбург': 'volga',
            'орск': 'volga',
            'бузулук': 'volga',
            'ижевск': 'volga',
            'сарапул': 'volga',

            // УрФО
            'екатеринбург': 'ural',
            'нижний тагил': 'ural',
            'каменск-уральский': 'ural',
            'первоуральск': 'ural',
            'челябинск': 'ural',
            'магнитогорск': 'ural',
            'миасс': 'ural',
            'златоуст': 'ural',
            'копейск': 'ural',
            'курган': 'ural',
            'тюмень': 'ural',
            'тобольск': 'ural',
            'сургут': 'ural',
            'нижневартовск': 'ural',
            'ханты-мансийск': 'ural',
            'нефтеюганск': 'ural',
            'новый уренгой': 'ural',
            'ноябрьск': 'ural',
            'надым': 'ural',
            'салехард': 'ural',

            // СФО
            'новосибирск': 'siberian',
            'бердск': 'siberian',
            'омск': 'siberian',
            'томск': 'siberian',
            'северск': 'siberian',
            'красноярск': 'siberian',
            'ачинск': 'siberian',
            'норильск': 'siberian',
            'иркутск': 'siberian',
            'ангарск': 'siberian',
            'братск': 'siberian',
            'кемерово': 'siberian',
            'новокузнецк': 'siberian',
            'прокопьевск': 'siberian',
            'киселевск': 'siberian',
            'междуреченск': 'siberian',
            'барнаул': 'siberian',
            'бийск': 'siberian',
            'рубцовск': 'siberian',
            'горно-алтайск': 'siberian',
            'абакан': 'siberian',
            'кызыл': 'siberian',

            // ДФО
            'улан-удэ': 'farEast',
            'чита': 'farEast',
            'якутск': 'farEast',
            'благовещенск': 'farEast',
            'владивосток': 'farEast',
            'артем': 'farEast',
            'уссурийск': 'farEast',
            'находка': 'farEast',
            'хабаровск': 'farEast',
            'комсомольск-на-амуре': 'farEast',
            'южно-сахалинск': 'farEast',
            'магадан': 'farEast',
            'петропавловск-камчатский': 'farEast'
        };


        const AMBIGUOUS_CITIES = [
            'советск',
            'троицк',
            'заречный',
            'мирный',
            'приморск',
            'красный яр',
            'никольск',
            'красноармейск',
            'лесной',
            'октябрьский',
            'первомайский',
            'радужный',
            'светлый',
            'северный',
            'центральный'
        ];


        function normalizeRegion(value) {

            return String(value || '')
                .toLowerCase()
                .replace(/ё/g, 'е')
                .replace(/[()"«»]/g, '')
                .replace(/(^|\s)г\.(?=\s|$)/giu, ' ')
                .replace(
                    /(^|\s)(область|обл\.?|республика|респ\.?|край|ао|автономный округ|автономная область|город)(?=\s|$)/giu,
                    ' '
                )
                .replace(/\s+/g, ' ')
                .trim();
        }


        function normalizeCity(value) {

            return String(value || '')
                .toLowerCase()
                .replace(/ё/g, 'е')
                .replace(/[()"«»]/g, '')
                .replace(/(^|\s)г\.?(?=\s|$)/giu, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        }


        function districtByRegion(region) {

            const value =
                normalizeRegion(region);

            if (!value) {
                return null;
            }

            if (REGION_EXACT[value]) {
                return REGION_EXACT[value];
            }

            for (
                const [stem, district]
                of Object.entries(REGION_STEMS)
            ) {
                if (
                    value.includes(stem)
                ) {
                    return district;
                }
            }

            return null;
        }


        function districtByCity(city) {

            const value =
                normalizeCity(city);

            if (!value) {
                return null;
            }

            if (
                AMBIGUOUS_CITIES.includes(
                    value
                )
            ) {
                return {
                    needRegion: true
                };
            }

            return CITY_MAP[value] || null;
        }


        function calcDistrictName(
            city,
            region
        ) {

            const byRegion =
                districtByRegion(region);

            if (byRegion) {

                return {
                    status: 'ok',
                    districtKey:
                        byRegion,
                    districtName:
                        DISTRICT_NAMES[
                            byRegion
                        ] || '',
                    source: 'region'
                };
            }

            const byCity =
                districtByCity(city);

            if (
                byCity &&
                typeof byCity === 'object' &&
                byCity.needRegion
            ) {
                return {
                    status:
                        'need_region',
                    districtKey: null,
                    districtName: '',
                    source: 'city'
                };
            }

            if (byCity) {

                return {
                    status: 'ok',
                    districtKey:
                        byCity,
                    districtName:
                        DISTRICT_NAMES[
                            byCity
                        ] || '',
                    source: 'city'
                };
            }

            return {
                status: 'unknown',
                districtKey: null,
                districtName: '',
                source: null
            };
        }


        function getEnumItems(
            fieldMeta
        ) {

            if (!fieldMeta) {
                return [];
            }

            return (
                fieldMeta.items ||
                fieldMeta.ITEMS ||
                fieldMeta.list ||
                fieldMeta.LIST ||
                fieldMeta.values ||
                fieldMeta.VALUES ||
                []
            );
        }


        function parseFieldValue(
            rawValue,
            fieldMeta
        ) {

            if (
                Array.isArray(rawValue)
            ) {
                rawValue =
                    rawValue[0] || '';
            }

            if (
                rawValue &&
                typeof rawValue === 'object'
            ) {
                rawValue =
                    rawValue.VALUE ||
                    rawValue.value ||
                    rawValue.ID ||
                    rawValue.id ||
                    '';
            }

            const value =
                String(
                    rawValue || ''
                );

            const items =
                getEnumItems(
                    fieldMeta
                );

            const found =
                items.find(
                    item => {

                        const id =
                            String(
                                item.ID ||
                                item.id ||
                                item.VALUE_ID ||
                                ''
                            );

                        const text =
                            String(
                                item.VALUE ||
                                item.value ||
                                item.NAME ||
                                ''
                            );

                        return (
                            id === value ||
                            text === value
                        );
                    }
                );

            if (found) {
                return (
                    found.VALUE ||
                    found.value ||
                    found.NAME ||
                    value
                );
            }

            return value;
        }


        return {
            DISTRICT_NAMES,
            normalizeRegion,
            normalizeCity,
            calcDistrictName,
            parseFieldValue
        };
    }
);
