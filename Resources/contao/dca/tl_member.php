<?php

$GLOBALS['TL_DCA']['tl_member']['palettes']['default'] .= ';{cart},cartId';

$GLOBALS['TL_DCA']['tl_member']['fields']['cartId']['exclude'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['cartId']['inputType'] = 'text';
