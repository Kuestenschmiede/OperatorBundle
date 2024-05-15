<?php

$str = 'tl_news_archive';

$GLOBALS['TL_LANG'][$str]['pushUpcomingEvents'] = ['Select which upcoming "good" events that should notify subscribers with the push notification. for example. "today" or "next day".'];
$GLOBALS['TL_LANG'][$str]['subscriptionTypes'] = ['Subscription types', 'Choose the subscription types with the checkbox "Notify current gutes events" activated. Subscribers will be notified'];

$GLOBALS['TL_LANG'][$str]['references']['today'] = ['Today'];
$GLOBALS['TL_LANG'][$str]['references']['nextday'] = ['Tomorrow'];
$GLOBALS['TL_LANG'][$str]['references']['next2days'] = ['The next 2 days'];
$GLOBALS['TL_LANG'][$str]['references']['next3days'] = ['The next 3 days'];
$GLOBALS['TL_LANG'][$str]['references']['nextWeek'] = ['Next week'];
$GLOBALS['TL_LANG'][$str]['references']['nextMonth'] = ['Next Month'];

$GLOBALS['TL_LANG'][$str]['operator_legend'] = 'Gutes settings (gutesio/operator)';
$GLOBALS['TL_LANG'][$str]['gutesBlogTitle'] = ['Gutes push notification title', ' Add the title for the push notification, for example "Look out ~ New Events", this will only be sent if there are any gutes events'];
$GLOBALS['TL_LANG'][$str]['gutesBlogTeaser'] = ['Gutes short teaser', ' Add a few sentences for the push notification, for example "Check out local events that are happening tomorrow", this will only be sent if there are any gutes events'];
$GLOBALS['TL_LANG'][$str]['gutesBlogImage'] = ['Notification icon','Here you can store an icon that will be displayed in the notification banner'];
