<?php

namespace Acowebs\WCPA;

class Themes
{
    /**
     * [
     * 'css' => [
     * 'LabelColor' => '',
     * 'LabelSize' => '',
     * 'LineColor' => '',
     *
     * 'DescColor' => '',
     * 'DescSize' => '',
     *
     * 'BorderColor' => '',
     * 'BorderWidth' => '',
     *
     * 'InputBgColor' => '',
     * 'CheckLabelColor' => '',
     * 'CheckLabelSize' => '',
     * 'CheckBgColor' => '',
     * 'CheckTickColor' => '',
     * ],
     * 'conf' => [
     * 'LabelPosition' => '',
     * 'DescPosition' => '',
     * ]
     * ];
     */

    public function getThemes()
    {
        $common = [
            'conf' => [
                'LabelPosition' => 'above',
                'DescPosition' => 'above',
                'UploadField' => 'custom_1',
                'QuantityFieldStyle' => 'default'

            ],
            'css' => [
                'LeftLabelWidth' => '120px',
            ]

        ];

        $style0 = [
            'name' => 'No Custom Styles',
            'key' => 'style_0',
            'conf' => [

            ],
            'css' => [


            ]
        ];

        $style1 = [
            'name' => 'Style 1',
            'key' => 'style_1',
            'conf' => [

            ],
            'css' => [
                'SectionTitleSize' => '14px',

                'LabelSize' => '14px',
                'DescSize' => '13px',
                'ErrorSize' => '13px',

                'LabelWeight' => 'normal',
                'DescWeight' => 'normal',

                'BorderWidth' => "1px",
                'BorderRadius' => "6px",
                'InputHeight' => '45px',

                'CheckLabelSize' => '14px',
                'CheckBorderWidth' => '1px',
                'CheckWidth' => '20px',
                'CheckHeight' => '20px',
                'CheckBorderRadius' => '4px',

                'CheckButtonRadius' => '5px',
                'CheckButtonBorder' => '2px',


                'QtyWidth' => '100px',
                'QtyHeight' => '45px',
                'QtyRadius' => '6px',


            ]
        ];

        $style2 = [
            'name' => 'Style 2',
            'key' => 'style_2',
            'conf' => [

            ],
            'css' => [
                'SectionTitleSize' => '18px',

                'LabelSize' => '14px',
                'DescSize' => '14px',
                'ErrorSize' => '14px',

                'LabelWeight' => 'normal',
                'DescWeight' => 'normal',

                'BorderWidth' => "1px",
                'BorderRadius' => "6px",
                'InputHeight' => '45px',

                'CheckLabelSize' => '14px',
                'CheckBorderWidth' => '1px',
                'CheckWidth' => '20px',
                'CheckHeight' => '20px',
                'CheckBorderRadius' => '6px',

                'CheckButtonRadius' => '6px',
                'CheckButtonBorder' => '1px',

                'QtyWidth' => '100px',
                'QtyHeight' => '45px',
                'QtyRadius' => '6px',
            ]
        ];
        $style3 = [
            'name' => 'Style 3',
            'key' => 'style_3',
            'conf' => [

            ],
            'css' => [
                'SectionTitleSize' => '16px',

                'LabelSize' => '12px',
                'DescSize' => '12px',
                'ErrorSize' => '12px',

                'LabelWeight' => 'normal',
                'DescWeight' => 'normal',

                'BorderWidth' => "1px",
                'BorderRadius' => "4px",
                'InputHeight' => '40px',

                'CheckLabelSize' => '14px',
                'CheckBorderWidth' => '1px',
                'CheckWidth' => '20px',
                'CheckHeight' => '20px',
                'CheckBorderRadius' => '4px',

                'CheckButtonRadius' => '5px',
                'CheckButtonBorder' => '1px',

                'QtyWidth' => '100px',
                'QtyHeight' => '40px',
                'QtyRadius' => '4px',
            ]
        ];

        /**
         * Grey
         */
        $color1 = [
            'name' => 'Color 1',
            'key' => 'color_1',
            'conf' => [

            ],
            'css' => [
                'SectionTitleColor' => '#4A4A4A',
                'SectionTitleBg' => '#EEEEEE47',

                'LineColor' => '#Bebebe',
                'ButtonColor' => '#3340d3',
                'LabelColor' => '#424242',
                'DescColor' => '#797979',


                'BorderColor' => "#c6d0e9",
                'BorderColorFocus' => "#3561f3",
                'InputBgColor' => '#FFFFFF',
                'InputColor' => '#5d5d5d',


                'CheckLabelColor' => '#4a4a4a',

                'CheckBgColor' => '#3340d3',
                'CheckBorderColor' => '#B9CBE3',
                'CheckTickColor' => '#ffffff',

                'RadioBgColor' => '#3340d3',
                'RadioBorderColor' => '#B9CBE3',
                'RadioSelBorderColor' => '#3340d3',

                'ButtonTextColor' => '#ffffff',

                'ErrorColor' => '#F55050',

                'CheckButtonColor' => '#CAE2F9',
                'CheckButtonBorderColor' => '#EEEEEE',
                'CheckButtonSelectionColor' => '#CECECE',




                'ImageSelectionOutline' => '#3340d3',

                'ImageTickBg' => '#2649FF',
                'ImageTickColor' => '#FFFFFF',
                'ImageTickBorder' => '#FFFFFF',

                'ImageMagnifierBg' => '#2649FF',
                'ImageMagnifierColor' => '#ffffff',
                'ImageMagnifierBorder' => '#FFFFFF',

                'ImageSelectionShadow' => '#00000040',


                'CheckToggleBg' => '#CAE2F9',
                'CheckToggleCircleColor' => '#FFFFFF',
                'CheckToggleBgActive' => '#BADA55',

                'QtyButtonColor' => '#EEEEEE',
                'QtyButtonHoverColor' => '#DDDDDD',
                'QtyButtonTextColor' => '#424242',

            ]
        ];


        /**
         * Grey
         */
        $color2 = [
            'name' => 'Color 2',
            'key' => 'color_2',
            'conf' => [

            ],
            'css' => [
                'SectionTitleColor' => '#4A4A4A',
                'SectionTitleBg' => '#EEEEEE47',

                'LineColor' => '#Bebebe',
                'ButtonColor' => '#4a4a4a',
                'LabelColor' => '#424242',
                'DescColor' => '#797979',

                'BorderColor' => "#Bebebe",
                'BorderColorFocus' => "#7a7a7a",
                'InputBgColor' => '#FFFFFF',
                'InputColor' => '#5d5d5d',

                'CheckLabelColor' => '#4a4a4a',

                'CheckBgColor' => '#4a4a4a',
                'CheckBorderColor' => '#A3a3a3',
                'CheckTickColor' => '#Ffffff',

                'RadioBgColor' => '#4a4a4a',
                'RadioBorderColor' => '#A3a3a3',
                'RadioSelBorderColor' => '#4a4a4a',

                'ButtonTextColor' => '#Ffffff',

                'ErrorColor' => '#F55050',

                'CheckButtonColor' => '#Eeeeee',
                'CheckButtonBorderColor' => '#Ffffff',
                'CheckButtonSelectionColor' => '#4a4a4a',



                'ImageSelectionOutline' => '#4a4a4a',

                'ImageTickBg' => '#4a4a4a',
                'ImageTickColor' => '#FFFFFF',
                'ImageTickBorder' => '#FFFFFF',

                'ImageMagnifierBg' => '#4a4a4a',
                'ImageMagnifierColor' => '#ffffff',
                'ImageMagnifierBorder' => '#FFFFFF',

                'ImageSelectionShadow' => '#00000040',


                'CheckToggleBg' => '#Cecece',
                'CheckToggleCircleColor' => '#FFFFFF',
                'CheckToggleBgActive' => '#4a4a4a',

                'QtyButtonColor' => '#EEEEEE',
                'QtyButtonHoverColor' => '#DDDDDD',
                'QtyButtonTextColor' => '#424242',

            ]
        ];

        /**
         * Blue
         */
        $color3 = [
            'name' => 'Color 3',
            'key' => 'color_3',
            'conf' => [

            ],
            'css' => [

                'SectionTitleColor' => '#515F8E',
                'SectionTitleBg' => '#EEEEEE47',

                'LineColor' => '#c6d0e9',
                'ButtonColor' => '#3340d3',
                'LabelColor' => '#413d96',
                'DescColor' => '#7a7fa8',

                'BorderColor' => "#c6d0e9",
                'BorderColorFocus' => "#3561f3",
                'InputBgColor' => '#FFFFFF',
                'InputColor' => '#515F8E',

                'CheckLabelColor' => '#4f499b',

                'CheckBgColor' => '#3340d3',
                'CheckBorderColor' => '#B9CBE3',
                'CheckTickColor' => '#ffffff',

                'RadioBgColor' => '#3340d3',
                'RadioBorderColor' => '#B9CBE3',
                'RadioSelBorderColor' => '#3340d3',

                'ButtonTextColor' => '#ffffff',

                'ErrorColor' => '#F55050',

                'CheckButtonColor' => '#CAE2F9',
                'CheckButtonBorderColor' => '#EEEEEE',
                'CheckButtonSelectionColor' => '#CECECE',



                'ImageSelectionOutline' => '#4f499b',

                'ImageTickBg' => '#4f499b',
                'ImageTickColor' => '#FFFFFF',
                'ImageTickBorder' => '#FFFFFF',

                'ImageMagnifierBg' => '#4f499b',
                'ImageMagnifierColor' => '#ffffff',
                'ImageMagnifierBorder' => '#FFFFFF',

                'ImageSelectionShadow' => '#00000040',

                'CheckToggleBg' => '#CAE2F9',
                'CheckToggleCircleColor' => '#FFFFFF',
                'CheckToggleBgActive' => '#BADA55',

                'QtyButtonColor' => '#EEEEEE',
                'QtyButtonHoverColor' => '#DDDDDD',
                'QtyButtonTextColor' => '#424242',


            ]
        ];
        /**
         * Violet
         */
        $color4 = [
            'name' => 'Color 4',
            'key' => 'color_4',
            'conf' => [

            ],
            'css' => [

                'SectionTitleColor' => '#515F8E',
                'SectionTitleBg' => '#EEEEEE47',

                'LineColor' => '#C9b1e2',
                'ButtonColor' => '#642bc2',
                'LabelColor' => '#6d21bb',
                'DescColor' => '#7242b6bf',

                'BorderColor' => "#C9b1e2",
                'BorderColorFocus' => "#7a13bd",
                'InputBgColor' => '#F6f6fa',
                'InputColor' => '#515F8E',

                'CheckLabelColor' => '#7c4fb4',

                'CheckBgColor' => '#7e46b8',
                'CheckBorderColor' => '#B9CBE3',
                'CheckTickColor' => '#ffffff',

                'RadioBgColor' => '#7e46b8',
                'RadioBorderColor' => '#B9CBE3',
                'RadioSelBorderColor' => '#7e46b8',

                'ButtonTextColor' => '#ffffff',

                'ErrorColor' => '#F55050',

                'CheckButtonColor' => '#CAE2F9',
                'CheckButtonBorderColor' => '#EEEEEE',
                'CheckButtonSelectionColor' => '#CECECE',



                'ImageSelectionOutline' => '#7e46b8',

                'ImageTickBg' => '#2649FF',
                'ImageTickColor' => '#FFFFFF',
                'ImageTickBorder' => '#FFFFFF',

                'ImageMagnifierBg' => '#2649FF',
                'ImageMagnifierColor' => '#ffffff',
                'ImageMagnifierBorder' => '#FFFFFF',

                'ImageSelectionShadow' => '#00000040',


                'CheckToggleBg' => '#CAE2F9',
                'CheckToggleCircleColor' => '#FFFFFF',
                'CheckToggleBgActive' => '#BADA55',

                'QtyButtonColor' => '#EEEEEE',
                'QtyButtonHoverColor' => '#DDDDDD',
                'QtyButtonTextColor' => '#424242',

            ]
        ];
        /**
         * Green
         */
        $color5 = [
            'name' => 'Color 5',
            'key' => 'color_5',
            'conf' => [

            ],
            'css' => [
                'SectionTitleColor' => '#515F8E',
                'SectionTitleBg' => '#EEEEEE47',

                'LineColor' => '#Eaeaee',
                'ButtonColor' => '#89c049',
                'LabelColor' => '#474747',
                'DescColor' => '#565a53',

                'BorderColor' => "#C8cfc1",
                'BorderColorFocus' => "#7ed321",
                'InputBgColor' => '#Ffffff',
                'InputColor' => '#525252',

                'CheckLabelColor' => '#616b55',

                'CheckBgColor' => '#89c049',
                'CheckBorderColor' => '#C8cfc1',
                'CheckTickColor' => '#Ffffff',

                'RadioBgColor' => '#89c049',
                'RadioBorderColor' => '#C8cfc1',
                'RadioSelBorderColor' => '#89c049',

                'ButtonTextColor' => '#Ffffff',

                'ErrorColor' => '#F55050',

                'CheckButtonColor' => '#C8cfc130',
                'CheckButtonBorderColor' => '#C8cfc1',
                'CheckButtonSelectionColor' => '#89c049',


                'ImageSelectionOutline' => '#89c049',

                'ImageTickBg' => '#2649FF',
                'ImageTickColor' => '#FFFFFF',
                'ImageTickBorder' => '#FFFFFF',

                'ImageMagnifierBg' => '#2649FF',
                'ImageMagnifierColor' => '#ffffff',
                'ImageMagnifierBorder' => '#FFFFFF',

                'ImageSelectionShadow' => '#00000040',


                'CheckToggleBg' => '#Dde7d2',
                'CheckToggleCircleColor' => '#FFFFFF',
                'CheckToggleBgActive' => '#89c049',

                'QtyButtonColor' => '#EEEEEE',
                'QtyButtonHoverColor' => '#DDDDDD',
                'QtyButtonTextColor' => '#424242',
            ]
        ];

        /**
         * light
         */
        $color6 = [
            'name' => 'Color 6',
            'key' => 'color_6',
            'conf' => [

            ],
            'css' => [

                'SectionTitleColor' => '#4e5761',
                'SectionTitleBg' => '#Ffffff47',

                'LineColor' => '#C5d1d3',
                'ButtonColor' => '#303fa7',
                'LabelColor' => '#4e5055',
                'DescColor' => '#9b9b9b',

                'BorderColor' => "#C5d1d3",
                'BorderColorFocus' => "#C3c3c3",
                'InputBgColor' => '#F9fafa',
                'InputColor' => '#515F8E',

                'CheckLabelColor' => '#4e5055',

                'CheckBgColor' => '#70a1df',
                'CheckBorderColor' => '#B9CBE3',
                'CheckTickColor' => '#Ffffff',

                'RadioBgColor' => '#70a1df',
                'RadioBorderColor' => '#B9CBE3',
                'RadioSelBorderColor' => '#70a1df',

                
                'ButtonTextColor' => '#Ffffff',

                'ErrorColor' => '#F55050',

                'CheckButtonColor' => '#Eff1f3',
                'CheckButtonBorderColor' => '#C5d1d3',
                'CheckButtonSelectionColor' => '#CECECE',



                'ImageSelectionOutline' => '#70a1df',

                'ImageTickBg' => '#2649FF',
                'ImageTickColor' => '#FFFFFF',
                'ImageTickBorder' => '#FFFFFF',

                'ImageMagnifierBg' => '#2649FF',
                'ImageMagnifierColor' => '#ffffff',
                'ImageMagnifierBorder' => '#FFFFFF',

                'ImageSelectionShadow' => '#00000040',


                'CheckToggleBg' => '#Eff1f3',
                'CheckToggleCircleColor' => '#FFFFFF',
                'CheckToggleBgActive' => '#8ab8e2',

                'QtyButtonColor' => '#EEEEEE',
                'QtyButtonHoverColor' => '#DDDDDD',
                'QtyButtonTextColor' => '#424242',
            ]
        ];

        return [
            'common' => $common,
            'styles' => [$style0, $style1, $style2, $style3],
            'colors' => [$color1, $color2, $color3, $color4, $color5, $color6]
        ];

    }
}
