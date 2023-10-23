<?php

$LNG['_SECURITY_STATUS'] = array(
	301 => 'Moved Permanently',
	302 => 'Found',
	303 => 'See Other',
	304 => 'Not Modified',

	400 => 'Bad Request',
	401 => 'Onbevoegd',
	402 => 'Payment Required',
	403 => 'Verboden',
	404 => 'Niet Gevonden',
	405 => 'Method Not Allowed',
	409 => 'Conflict',
	410 => 'Gone',
	428 => 'Precondition Required',
	429 => 'Too Many Requests',
	431 => 'Request Header Fields Too Large',

	500 => 'Internal Server Error',
	503 => 'Service Unavailable',
	511 => 'Network Authentication Required',

	800 => 'Bad IP',
	801 => 'Spam url in referer header',
	802 => 'Unknown user-agent',
	803 => 'Flood Protection',
);
$LNG['_SECURITY_MSG'] = array(
	# Redirection
	301 => 'De opgegeven URL, is permanent verhuisd naar een nieuwe locatie. Toekomstige verwijzingen naar deze pagina kunnen de nieuwe locatie gebruiken.',
	302 => 'De opgegeven URL, is tijdelijk verhuisd naar een nieuwe locatie. Verwijzingen naar deze pagina kunnen de oude locatie blijven gebruiken.',
	303 => 'De opgegeven URL, is waarschijnlijk verplaatst naar een ander locatie. Toekomstige verwijzingen naar deze pagina kunnen de nieuwe locatie gebruiken.',
	# Client Errors
	400 => 'De opgegeven URL, was een slecht verzoek',
	401 => 'De opgegeven URL, vereist autorisatie om de pagina te kunnen bekijken.',
	402 => 'De opgegeven URL, vereist een betaling om de pagina te kunnen bekijken.',
	403 => 'De toegang tot de opgegeven URL, is verboden.',
	404 => "De opgegeven URL, kon niet gevonden worden. Mogelijk is er een typfout gemaakt in de URL, of is er sprake van een foute link.\n\nWe hebben deze melding opgeslagen en proberen dit probleem zo spoedig mogelijk op te lossen.",
	405 => 'De opgegeven URL, accepteert de opgegeven request method niet.',
	409 => 'The request could not be completed due to a conflict with the current state of the resource.',
	410 => 'De opgegeven URL, bestaat niet meer. Mogelijk is er een typfout gemaakt in de URL, of is er sprake van een foute link.',
	428 => '',
	429 => '',
	431 => '',
	# Server Errors
	500 => "De opgegeven URL, %s, zorgde voor een server error. Probeer de pagina nogmaals op te vragen.\n\nWe hebben het probleem opgeslagen en zullen het probleem verhelpen.",
	503 => 'De opgegeven URL, %s, is tijdelijk niet beschikbaar door tijdelijke overbelasting of onderhoud aan de server.',
	511 => '',
	# Security Errors
	800 => 'U kunt de website niet bezoeken vanwege een negatief IP-adres.',
	801 => 'U kunt de website niet bezoeken omdat u vanuit een spam URL op deze website gekomen bent.',
	802 => 'U kunt de website niet bezoeken vanwege een onbekende user-agent: '.$_SERVER['HTTP_USER_AGENT'].'.',
	803 => 'U kunt de website niet bezoeken omdat u de overbelasting waarschuwingen negeert.',

	'_FLOOD' => "Het is niet toegestaan het systeem te onnodig te belasten.\nU kunt de website weer bekijken na %s seconden.",
	'Last_warning' => 'Dit is de laatste waarschuwing. Bij herhaling kunt u de website niet meer bezoeken.',
	'_EXPIRED' => 'De %s is afgelopen. De pagina wordt opnieuw ingeladen om deze opnieuw in te stellen.',
);
