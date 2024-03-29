import React from 'react';
import { __, data_pointer } from 'crewhrm-materials/helpers.jsx';
import { applyFilters } from 'crewhrm-materials/hooks.jsx';

const _contents = window[data_pointer].settings?.contents || {};

const conte_dropdown = Object.keys(_contents).map(content_type=>{
	const {enable, label} = _contents[content_type];
	return ! enable ? null : {
		id: content_type,
		label: label
	}
}).filter(f=>f);

const reaction_modes = [
	{
		id: 'like',
		label: __('Like')
	},
	{
		id: 'rating',
		label: __('Rating')
	},
	{
		id: 'none',
		label: __('None')
	}
];

export const settings_fields = applyFilters(
	'solidie_setting_fields',
	{
		general: {
			label: __('General Settings'),
			description: __('Configure all the content management, sales and contributor related settings in one place'),
			segments: {
				gallery: {
					label: __('Gallery'),
					description: __('Gallery and single page settings'),
					fields: [
						{
							name: 'free_download_label',
							label: __('Free download label'),
							type: 'text',
							placeholder: __('Free')
						},
						{
							name: 'free_download_description',
							label: __('Free download description'),
							type: 'textarea',
							placeholder: __('This content is eligible to download for free')
						}
					]
				}
			}
		},
		contents: {
			label: __('Content Types'),
			description: __('Configure the content types you\'d like to showcase'),
			segments: {
				
			}
		},
	}
);
