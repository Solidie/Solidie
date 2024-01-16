import React, { useContext, useState } from 'react';

import { request } from 'crewhrm-materials/request.jsx';
import { __, data_pointer, isEmpty } from 'crewhrm-materials/helpers.jsx';
import { ToggleSwitch } from 'crewhrm-materials/toggle-switch/ToggleSwitch.jsx';
import { TextField } from 'crewhrm-materials/text-field/text-field.jsx';
import { ContextToast } from 'crewhrm-materials/toast/toast.jsx';
import { LoadingIcon } from 'crewhrm-materials/loading-icon/loading-icon.jsx';
import { Modal } from 'crewhrm-materials/modal.jsx';
import { DropDown } from 'crewhrm-materials/dropdown/dropdown.jsx';
import { DoAction } from 'crewhrm-materials/mountpoint.jsx';

import table_style from 'solidie-materials/styles/table.module.scss';
import style from './contents.module.scss';

export const getFlattenedCategories=(categories=[], exclude_level=null)=>{
	const options = [];

	// Flatten nested array to linear
	const flattener = (cats=[], level=0) => {
		for ( let i=0; i<cats.length; i++ ) {
			const {category_id, category_name, children=[]} = cats[i];

			// Exclude self and children for editor dropdown
			if ( exclude_level == category_id ) {
				continue;
			}

			options.push({
				...cats[i],
				id: category_id,
				label: '—'.repeat(level)+ ' ' + category_name,
				level
			});
			flattener(children, level+1);
		}
	}
	flattener(categories);

	return options;
}

export function ContentSettings(props) {
	const {content_list={}, categories={}, contents={}} = props;
	const {ajaxToast} = useContext(ContextToast);

	const [state, setState] = useState({
		saving: false,
		contents: contents
	});

	const [catState, setCatState] = useState({
		saving: false,
		categories: categories,
		editor: null
	});

	const openCatEditor=(category=null)=>{
		setCatState({
			...catState,
			editor: category
		});
	}

	const setCatValue=(name, value)=>{
		setCatState({
			...catState,
			editor: {
				...catState.editor,
				[name]: value
			}
		});
	}

	const saveCat=()=>{
		const {editor} = catState;
		setCatState({
			...catState,
			saving: true
		});

		request('saveCategory', editor, resp=>{
			const {success, data:{categories=catState.categories}} = resp;
			
			ajaxToast(resp);
			
			setCatState({
				...catState,
				categories,
				saving: false,
				editor: success ? null : catState.editor
			});
		});
	}

	const deleteCategory=(category_id)=>{
		if ( ! window.confirm( __( 'Sure to delete' ) ) ) {
			return;
		}

		request('deleteCategory', {category_id}, resp=>{
			const {success, data:{categories=[]}} = resp;

			if ( ! success ) {
				ajaxToast(resp);
				return;
			}

			setCatState({
				...catState,
				categories
			});
		});
	}

	const saveOptions=()=>{
		setState({...state, saving: true});
		request('saveContentTypes', {'content_types': state.contents}, resp=>{
			setState({...state, saving: false});
			ajaxToast(resp);
		});
	}

	const onChangeContents=(content_key, name, value)=>{
		const {contents={}} = state;

		setState({
			...state,
			contents:{
				...contents,
				[content_key]: {
					...contents[content_key],
					[name]: value
				}
			}
		});
	}

	const {has_pro} = window[data_pointer];
	const cat_options = catState.editor !== null ? getFlattenedCategories(catState.categories[catState.editor.content_type], catState.editor.category_id) : null;
	const col_style = {
		className: `${has_pro ? 'col-20' : 'col-33'}`.classNames(style), 
		style : {
			textAlign: 'left'
		}
	};

	return <> 
		{
			catState.editor===null ? null : 
			<Modal 
				closeOnDocumentClick={true} 
				nested={true} 
				onClose={()=>openCatEditor(null)}
			>
				<div>
					<strong className={'d-block margin-bottom-8'.classNames()}>
						{__('Category Name')}
					</strong>
					<TextField
						value={catState.editor.category_name || ''}
						onChange={v=>setCatValue('category_name', v)}/>
				</div>
				<br/>

				{
					isEmpty(cat_options) ? null : <>
						<div>
							<strong className={'d-block margin-bottom-8'.classNames()}>
								{__('Parent Category')}
							</strong>
							<DropDown 
								value={catState.editor.parent_id}
								options={cat_options}
								onChange={id=>setCatValue('parent_id', id)}/>
						</div>
						<br/>
					</>
				}

				<div className={'text-align-right'.classNames()}>
					<button onClick={()=>openCatEditor(null)} className={'button button-outlined'.classNames()}>
						{__('Cancel')}
					</button>
					&nbsp;
					&nbsp;
					<button 
						className={'button button-primary'.classNames()} 
						onClick={saveCat}
						disabled={isEmpty(catState.editor.category_name)}
					>
						{!catState.editor.category_id ? __('Create') : __('Update')} <LoadingIcon show={catState.saving}/>
					</button>
				</div>
			</Modal>
		}

		<div className={'padding-15 bg-color-white'.classNames()}>
			<strong className={'d-block font-size-24 font-weight-600'.classNames()}>
				{__('Manage content types')}
			</strong>
			<span className={'d-block margin-top-10 font-size-14 color-text-light'.classNames()}>
				{__('Enable and configure the content types that would like to showcase to the world')}
			</span>
			<br/>
			<br/>

			<table className={'table'.classNames(table_style)}>
				<thead>
					<tr>
						<th {...col_style}>{__('Content')}</th>
						<th {...col_style}>{__('Base URL Slug')}</th>
						<th {...col_style}>{__('Categories')}</th>
						{has_pro ? <th {...col_style}>{__('Monetization Plans')}</th> : null}
					</tr>
				</thead>
				<tbody>
					{
						// Loop through hard coded content_list, so no risk to show unwanted things from database
						Object.keys(content_list).map(c_type=>{
							const {label, description, slug: default_slug} = content_list[c_type];
							const {enable=false} = state.contents?.[c_type] || {};
							const categories = getFlattenedCategories(catState.categories[c_type] || []);
							const base_slug = state.contents?.[c_type]?.slug || default_slug;

							return <tr key={c_type} style={{verticalAlign: 'top'}}>
								<td data-th={__('Content Type')} {...col_style}>
									<div className={'d-flex column-gap-15'.classNames()}>
										<div>
											<ToggleSwitch 
												disabled={state.saving}
												checked={enable}
												onChange={checked=>onChangeContents(c_type, 'enable', checked)} />
										</div>
										<div className={'flex-1'.classNames()}>
											{label} <br/>
											<small>{description}</small>
										</div>
									</div>
								</td>
								<td data-th={__('Base URL Slug')} {...col_style}>
									<div>
										<TextField
										disabled={state.saving}
										value={base_slug}
										onChange={v=>onChangeContents(c_type, 'slug', v)}
										style={{height:'30px'}}/>
									</div>
								</td>
								<td data-th={__('Categories')} {...col_style}>
									<div>
										{
											categories.map(category=>{
												const {label, category_id} = category;
												return <div key={category_id} className={'d-flex align-items-center column-gap-15'.classNames() + 'category-single'.classNames(style)}>
													{label} <span className={'d-inline-flex align-items-center column-gap-8'.classNames() + 'actions'.classNames(style)}>
														<i className={'ch-icon ch-icon-edit-2 cursor-pointer'.classNames()} onClick={()=>openCatEditor(category)}></i>
														<i className={'ch-icon ch-icon-trash cursor-pointer'.classNames()} onClick={()=>deleteCategory(category_id)}></i>
													</span>
												</div>
											})
										}

										<div className={"d-flex align-items-center column-gap-10".classNames()}>
											<span 
												onClick={()=>openCatEditor({content_type: c_type})} 
												className={`cursor-pointer hover-underline ${categories.length ? 'border-top-1 b-color-tertiary' : ''}`.classNames()}
												style={categories.length ? {paddingTop: '6px', marginTop: '6px'} : {}}
											>
												{__('+ Add Category')}
											</span>
										</div>
									</div>
									
								</td>
								{
									! has_pro ? null : <td data-th={__('Monteziation Plans')} {...{col_style, className: 'col-40'.classNames(style)}}>
										<div style={{width: '100%'}}>
											<DoAction 
												action="content_settings_plans_column" 
												payload={{
													content_type: c_type,
													content: state.contents[c_type],
													onChange: (name, value)=>onChangeContents(c_type, name, value)
												}}
											/>
										</div>
									</td>
								}
							</tr>
						})
					}
				</tbody>
			</table>
			<br/>
			<div className={'text-align-right'.classNames()}>
				<button 
					className={'button button-primary'.classNames()}
					onClick={saveOptions} 
					disabled={state.saving}
				>
					{__('Update Content Types')} <LoadingIcon show={state.saving}/>
				</button>
			</div>
		</div>
	</>
}
