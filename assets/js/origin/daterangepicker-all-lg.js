/* German initialisation for the jQuery UI date picker plugin.
Written by Milian Wolff (mail@milianw.de).

    $.datepicker.regional['de'] = {clearText: 'löschen', clearStatus: 'aktuelles Datum löschen',
            closeText: 'schließen', closeStatus: 'ohne Änderungen schließen',
            prevText: '&#x3c;zurück', prevStatus: 'letzten Monat zeigen',
            nextText: 'Vor&#x3e;', nextStatus: 'nächsten Monat zeigen',
            currentText: 'heute', currentStatus: '',
            monthNames: ['Januar','Februar','März','April','Mai','Juni',
            'Juli','August','September','Oktober','November','Dezember'],
            monthNamesShort: ['Jan','Feb','Mär','Apr','Mai','Jun',
            'Jul','Aug','Sep','Okt','Nov','Dez'],
            monthStatus: 'anderen Monat anzeigen', yearStatus: 'anderes Jahr anzeigen',
            weekHeader: 'Wo', weekStatus: 'Woche des Monats',
            dayNames: ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'],
            dayNamesShort: ['So','Mo','Di','Mi','Do','Fr','Sa'],
            dayNamesMin: ['So','Mo','Di','Mi','Do','Fr','Sa'],
            dayStatus: 'Setze DD als ersten Wochentag', dateStatus: 'Wähle D, M d',
            dateFormat: 'dd.mm.yy', firstDay: 1,
            initStatus: 'Wähle ein Datum', isRTL: false}; */
jQuery(function($){
    $.datepicker.regional['cs'] = {
            monthNames: ['Leden','Únor','Březen','Duben','Květen','Červen',
            'Červenec','Srpen','Září','Říjen','Listopad','Prosinec'],
            monthNamesShort: ['Led','Úno','Bře','Dub','Kvě','Čer',
            'Čvc','Srp','Zář','Říj','Lis','Pro'],
            dayNames: ['Neděle','Pondělí','Úterý','Středa','Čtvrtek','Pátek','Sobota'],
            dayNamesShort: ['Ne','Po','Út','St','Čt','Pá','So'],
            dayNamesMin: ['Ne','Po','Út','St','Čt','Pá','So']};

    $.datepicker.regional['da'] = {
            monthNames: ['Januar','Februar','Marts','April','Maj','Juni',
            'Juli','August','September','Oktober','November','December'],
            monthNamesShort: ['Jan','Feb','Mar','Apr','Maj','Jun',
            'Jul','Aug','Sep','Okt','Nov','Dec'],
            dayNames: ['Søndag','Mandag','Tirsdag','Onsdag','Torsdag','Fredag','Lørdag'],
            dayNamesShort: ['Sø','Ma','Ti','On','To','Fr','Lø'],
            dayNamesMin: ['Sø','Ma','Ti','On','To','Fr','Lø']};

    $.datepicker.regional['de'] = {
            monthNames: ['Januar','Februar','März','April','Mai','Juni',
            'Juli','August','September','Oktober','November','Dezember'],
            monthNamesShort: ['Jan','Feb','Mär','Apr','Mai','Jun',
            'Jul','Aug','Sep','Okt','Nov','Dez'],
            dayNames: ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'],
            dayNamesShort: ['So','Mo','Di','Mi','Do','Fr','Sa'],
            dayNamesMin: ['So','Mo','Di','Mi','Do','Fr','Sa']};

    $.datepicker.regional['el'] = {
            monthNames: ['Ιανουάριος','Φεβρουάριος','Μάρτιος','Απρίλιος','Μάιος','Ιούνιος',
            'Ιούλιος','Αύγουστος','Σεπτέμβριος','Οκτώβριος','Νοέμβριος','Δεκέμβριος'],
            monthNamesShort: ['Ιαν','Φεβ','Μάρ','Απρ','Μάι','Ιού',
            'Ιού','Αύγ','Σεπ','Οκτ','Νοέ','Δεκ'],
            dayNames: ['Κυριακή','Δευτέρα','Τρίτη','Τετάρτη','Πέμπτη','Παρασκευή','Σάββατο'],
            dayNamesShort: ['Κυρ.','Δευτ.','Τρ.','Τετ.','Πεμ.','Παρ.','Σαβ.'],
            dayNamesMin: ['Κυ','Δε','Τρ','Τε','Πέ','Πα','Σά']};

    $.datepicker.regional['en'] = {};

    $.datepicker.regional['es'] = {
            monthNames: ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
            'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'],
            monthNamesShort: ['Ene','Feb','Mar','Abr','Mayo','Jun',
            'Jul','Ago','Sep','Oct','Nov','Dic'],
            dayNames: ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'],
            dayNamesShort: ['Do','Lu','Ma','Mi','Ju','Vi','Sá'],
            dayNamesMin: ['Do','Lu','Ma','Mi','Ju','Vi','Sá']};

    $.datepicker.regional['fi'] = {
            monthNames: ['Tammikuu','Helmikuu','Maaliskuu','Huhtikuu','Toukokuu','Kesäkuu',
            'Heinäkuu','Elokuu','Syyskuu','Lokakuu','Marraskuu','Joulukuu'],
            monthNamesShort: ['Tam','Hel','Maa','Huh','Tou','Kes',
            'Hei','Elo','Syy','Lok','Mar','Jou'],
            dayNames: ['Sunnuntai','Maanantai','Tiistai','Keskiviikko','Torstai','Perjantai','Lauantai'],
            dayNamesShort: ['Su','Ma','Ti','Ke','To','Pe','La'],
            dayNamesMin: ['Su','Ma','Ti','Ke','To','Pe','La']};

    $.datepicker.regional['fr'] = {
            monthNames: ['Janvier','Février','Mars','Avril','Mai','Juin',
            'Juillet','Août','Septembre','Octobre','Novembre','Décembre'],
            monthNamesShort: ['Janv','Févr','Mars','Avr','Mai','Juin',
            'Juil','Août','Sept','Oct','Nov','Déc'],
            dayNames: ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'],
            dayNamesShort: ['Di','Lu','Ma','Me','Je','Ve','Sa'],
            dayNamesMin: ['Di','Lu','Ma','Me','Je','Ve','Sa']};

    $.datepicker.regional['hr'] = {
            monthNames: ['Siječanj','Veljača','Ožujak','Travanj','Svibanj','Lipanj',
            'Srpanj','Kolovoz','Rujan','Listopad','Studeni','Prosinac'],
            monthNamesShort: ['Sij','Velj','Ožu','Tra','Svi','Lip',
            'Srp','Kol','Ruj','Lis','Stu','Pro'],
            dayNames: ['Nedjelja','Ponedjeljak','Utorak','Srijeda','Četvrtak','Petak','Subota'],
            dayNamesShort: ['Ne','Po','Ut','Sr','Če','Pe','Su'],
            dayNamesMin: ['Ne','Po','Ut','Sr','Če','Pe','Su']};

    $.datepicker.regional['hu'] = {
            monthNames: ['Január','Február','Március','Április','Május','Június',
            'Július','Augusztus','Szeptember','Október','November','December'],
            monthNamesShort: ['Jan','Feb','Már','Ápr','Máj','Jún',
            'Júl','Aug','Sze','Okt','Nov','Dec'],
            dayNames: ['Vasárnap','Hétfő','Kedd','Szerda','Csütörtök','Péntek','Szombat'],
            dayNamesShort: ['V','H','K','Sze','Cs','P','Szo'],
            dayNamesMin: ['V','H','K','Sze','Cs','P','Szo']};

    $.datepicker.regional['it'] = {
            monthNames: ['Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno',
            'Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'],
            monthNamesShort: ['Gen','Feb','Mar','Apr','Mag','Giu',
            'Lug','Ago','Sett','Ott','Nov','Dic'],
            dayNames: ['Domenica','Lunedi','Martedi','Mercoledi','Giovedi','Venerdi','Sabato'],
            dayNamesShort: ['Do','Lu','Ma','Me','Gi','Ve','Sa'],
            dayNamesMin: ['Do','Lu','Ma','Me','Gi','Ve','Sa']};

    $.datepicker.regional['nl'] = {
            monthNames: ['Januari','Februari','Maart','April','Mei','Juni',
            'Juli','Augustus','September','Oktober','November','December'],
            monthNamesShort: ['Jan','Feb','Maa','Apr','Mei','Jun',
            'Jul','Aug','Sep','Okt','Nov','Dec'],
            dayNames: ['Zondag','Maandag','Dinsdag','Woensdag','Donderdag','Vrijdag','Zaterdag'],
            dayNamesShort: ['Zo','Ma','Di','Wo','Do','Vr','Za'],
            dayNamesMin: ['Zo','Ma','Di','Wo','Do','Vr','Za']};

    $.datepicker.regional['pl'] = {
            monthNames: ['Styczeń','Luty','Marzec','Kwiecień','Maj','Czerwiec',
            'Lipiec','Sierpień','Wrzesień','Październik','Listopad','Grudzień'],
            monthNamesShort: ['Sty','Lut','Mar','Kwi','Maj','Cze',
            'Lip','Sie','Wrz','Paź','Lis','Gru'],
            dayNames: ['Niedziela','Poniedziałek','Wtorek','Środa','Czwartek','Piątek','Sobota'],
            dayNamesShort: ['Ni','Po','Wt','Śr','Cz','Pi','So'],
            dayNamesMin: ['Ni','Po','Wt','Śr','Cz','Pi','So']};

    $.datepicker.regional['pt'] = {
            monthNames: ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
            'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'],
            monthNamesShort: ['Jan','Fev','Mar','Abr','Mai','Jun',
            'Jul','Ago','Set','Out','Nov','Dez'],
            dayNames: ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'],
            dayNamesShort: ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'],
            dayNamesMin: ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb']};

    $.datepicker.regional['ru'] = {
            monthNames: ['Январь','Февраль','Март','Апрель','Май','Июнь',
            'Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'],
            monthNamesShort: ['Янв','Фев','Мар','Апр','Май','Июн',
            'Июл','Авг','Сен','Окт','Ноя','Дек'],
            dayNames: ['Воскресенье','Понедельник','Вторник','Среда','Четверг','Пятница','Суббота'],
            dayNamesShort: ['Вс','Пн','Вт','Ср','Чт','Пт','Сб'],
            dayNamesMin: ['Вс','Пн','Вт','Ср','Чт','Пт','Сб']};

    $.datepicker.regional['sv'] = {
            monthNames: ['Januari','Februari','Mars','April','Maj','Juni',
            'Juli','Augusti','September','Oktober','November','December'],
            monthNamesShort: ['Jan','Feb','Mar','Apr','Maj','Jun',
            'Jul','Aug','Sep','Okt','Nov','Dec'],
            dayNames: ['Söndag','Måndag','Tisdag','Onsdag','Torsdag','Fredag','Lördag'],
            dayNamesShort: ['Sö','Må','Ti','On','To','Fr','Lö'],
            dayNamesMin: ['Sö','Må','Ti','On','To','Fr','Lö']};

    $.datepicker.regional['tr'] = {
            monthNames: ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran',
            'Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'],
            monthNamesShort: ['Oca','Şub','Mar','Nis','May','Haz',
            'Tem','Ağu','Eyl','Eki','Kas','Ara'],
            dayNames: ['Pazar','Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi'],
            dayNamesShort: ['Paz','Pzt','Sal','Çrş','Prş','Cum','Cmt'],
            dayNamesMin: ['Paz','Pzt','Sal','Çrş','Prş','Cum','Cmt']};
});
function getDateFormat(lg)
{
    if (lg == 'nl') {
        return 'dd-mm-yy';
    } else if (lg == 'en' || lg == 'it' || lg == 'fr' || lg == 'es') {
        return 'dd/mm/yy';
    } else {
        return 'dd.mm.yy';
    }
}
