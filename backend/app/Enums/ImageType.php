<?php

namespace App\Enums;

/**
 * Design doc §2/§0: the seven categories every competitor's imaging module converges on for a
 * general dentistry practice. Deliberately does not include DICOM/CBCT-specific types — design doc
 * §7 decision 1, out of V1 scope.
 */
enum ImageType: string
{
    case IntraoralPhoto = 'intraoral_photo';
    case ExtraoralPhoto = 'extraoral_photo';
    case XrayPeriapical = 'xray_periapical';
    case XrayBitewing = 'xray_bitewing';
    case XrayPanoramic = 'xray_panoramic';
    case XrayCephalometric = 'xray_cephalometric';
    case Other = 'other';
}
