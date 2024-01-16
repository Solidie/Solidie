import React, { useContext, useEffect, useState } from 'react'

import { request } from 'crewhrm-materials/request.jsx';
import { __, data_pointer, sprintf, formatDate, isEmpty } from 'crewhrm-materials/helpers.jsx';
import { ContextToast } from 'crewhrm-materials/toast/toast.jsx';
import { LoadingIcon } from 'crewhrm-materials/loading-icon/loading-icon.jsx';
import { Pagination } from 'crewhrm-materials/pagination/pagination.jsx';
import { TextField } from 'crewhrm-materials/text-field/text-field.jsx';

import { Tabs } from 'solidie-materials/tabs/tabs.jsx';
import { TableStat } from 'solidie-materials/table-stat.jsx';
import { getDashboardPath } from 'solidie-materials/helpers.jsx';

import table_style from 'solidie-materials/styles/table.module.scss';
import style from './inventory.module.scss';

export function InventoryWrapper({children, content_label, catalog_permalink, navigate, params={}}) {

	const {content_type} = params;

	const _contents = window[data_pointer]?.settings?.contents || {};
	const enabled_contents = 
		Object.keys(_contents)
		.map(c=>{
			return _contents[c].enable ? {..._contents[c], content_type:c} : null
		})
		.filter(c=>c)
		.map(c=>{
			return {...c, id: c.content_type}
		});

	const [state, setState] = useState({
		error_message: null
	});

	useEffect(()=>{
		if ( ! content_type ) {
			const first = enabled_contents[0]?.content_type;
			if ( first ) {
				navigate(getDashboardPath('inventory/'+first), {replace: true});
			} else {
				setState({
					...state, 
					error_message: <div className={'text-align-center padding-vertical-40'.classNames()}>
						<span className={'d-block margin-bottom-10 font-size-20'.classNames()}>
							{__('No content type is enabled yet.')}
						</span>
						<a href={window[data_pointer]?.permalinks?.content_types} className={'button button-primary button-small'.classNames()}>
							{__('Enable Now')}
						</a>
					</div>
				});
			}
		} else if( ! enabled_contents.find(e=>e.content_type===content_type)?.enable ) {
			setState({
				...state, 
				error_message: <div className={'text-align-center padding-vertical-40'.classNames()}>
						<span className={'d-block margin-bottom-10 font-size-20'.classNames()}>
							{sprintf(__('The content type \'%s\' is not found or maybe disabled meanwhile'), content_type)}
						</span>
						<a href={window[data_pointer]?.permalinks?.content_types} className={'button button-primary button-small'.classNames()}>
							{__('Check Content Types')}
						</a>
					</div>
			});
		}
	}, []);

	return state.error_message || <div>
		<div>
			<strong className={"d-flex align-items-center column-gap-8 color-text padding-vertical-10 position-sticky top-0".classNames()}>
				<span className={'font-size-24 font-weight-600 letter-spacing-3'.classNames()}>
					{__('Inventory')} {content_label ? <> - <a href={catalog_permalink} target='_blank' className={'hover-underline'.classNames()}>{content_label}</a></> : null}
				</span>
			</strong>

			{
				enabled_contents.length < 2 ? null :
				<Tabs 
					tabs={enabled_contents} 
					active={content_type} 
					onChange={tab=>navigate(getDashboardPath('inventory/'+tab))}/>
			}
		</div>
		
		{children}
	</div>
}

export function Inventory({navigate, params={}}) {

	const {content_type} = params;
	const {ajaxToast} = useContext(ContextToast);

	const [state, setState] = useState({
		fetching: false,
		contents: [],
		segmentation: null,
		catalog_permalink: null,
		content_type: content_type
	});

	const filterStateInitial = {
		page: 1,
		limit: null,
		search: ''
	}
	const [filterState, setFilterState] = useState(filterStateInitial);

	const setFilter=(name, value)=>{
		setFilterState({
			...filterState,
			page: name=='search' ? 1 : filterState.page,
			[name]: value
		});
	}

	const fetchContents=(variables={})=>{
		setState({
			...state,
			fetching: true,
			...variables
		});

		const payload = {
			...filterState, 
			content_type, 
			segmentation: true, 
			order_by: 'newest'
		}

		request( 'getContentList', payload, resp=>{
			const {
				success, 
				data: {
					segmentation = {}, 
					contents=[],
					catalog_permalink
				}
			} = resp;

			setState({
				...state,
				contents,
				fetching: false,
				segmentation,
				catalog_permalink
			});
		} );
	}

	const deleteContent=(content_id)=>{
		if ( ! window.confirm('Sure to delete?') ) {
			return;
		}

		request('deleteContent', {content_id}, resp=>{
			if (!resp.success) {
				ajaxToast(resp);
			} else {
				fetchContents();
			}
		});
	}

	function Link({children, className, title, to}) {
		return <a 
			className={className} 
			title={title} 
			href={to}
			onClick={e=>{e.preventDefault(); navigate(to);}}
		>
			{children}
		</a>
	}

	useEffect(()=>{
		if ( content_type ) {
			fetchContents({
				content_type,
				contents: state.content_type!=content_type ? [] : state.contents
			});
		}
	}, [content_type, filterState]);

	useEffect(()=>{
		setFilterState(filterStateInitial);
	}, [content_type]);

	const _content = window[data_pointer]?.settings?.contents[content_type] || {};
	const _content_label = _content.label || __('Content');
	
	return <InventoryWrapper 
		content_label={_content_label} 
		content_type={content_type}
		catalog_permalink={state.catalog_permalink}
		navigate={navigate}
		params={params}
	>
		{
			// When no content created at all
			!state.contents.length ?
				<div className={'padding-vertical-40 text-align-center'.classNames()}>
					{
						state.fetching ? <div>
							<LoadingIcon center={true}/>
						</div>
						:
						<>
							<strong className={'d-block font-size-14 margin-bottom-20'.classNames()}>
								{sprintf(__('No %s found'), _content_label)}
							</strong>
							<Link to={getDashboardPath(`inventory/${content_type}/editor/new`)} className={'button button-primary button-small'.classNames()}>
								{__('Add New')}
							</Link>
						</>
						
					}
				</div> 
				: 
				<>
					<div className={'d-flex align-items-center margin-top-10 margin-bottom-10'.classNames()}>
						<div className={'flex-1'.classNames()}>
							<Link to={getDashboardPath(`inventory/${content_type}/editor/new`)}>
								<span className={'font-weight-500 cursor-pointer hover-underline'.classNames()}>
									<i className={'ch-icon ch-icon-add-circle'.classNames()}></i> {sprintf(__('Add New %s'), _content_label)} 
								</span>
							</Link>
						</div>
						
						<div className={'d-flex align-items-center column-gap-5'.classNames()}>
							<strong className={'white-space-nowrap'.classNames()}>
								{__('Search')}
							</strong>
							<TextField
								placeholder={__('Enter Keyword')}
								onChange={key=>setFilter('search', key)}
								value={filterState.search}
								style={{height: '35px'}}/>
						</div>
					</div>

					<table 
						className={'table'.classNames(style) + 'table'.classNames(table_style)} 
						style={{background: 'rgb(128 128 128 / 3.5%)'}}
					>
						<thead>
							<tr>
								<th>{__('Title')}</th>
								<th>{__('Category')}</th>
								<th>{__('Monetization')}</th>
								<th>{__('Status')}</th>
								<th>{__('Created')}</th>
							</tr>
						</thead>
						<tbody>
							{
								state.contents.map((content, idx) =>{
									let {
										content_id, 
										content_title, 
										content_url, 
										media, 
										created_at, 
										content_status, 
										category_name,
										product_id
									} = content;

									const thumbnail_url = media?.thumbnail?.file_url;

									return <tr key={content_id}>
										<td data-th={__('Content')} style={{paddingTop: '20px', paddingBottom: '20px'}}>
											<div className={'d-flex column-gap-15'.classNames()}>
												{
													!thumbnail_url ? null :
													<div>
														<img 
															src={thumbnail_url} 
															style={{width: '30px', height: 'auto', borderRadius: '2px'}}/>
													</div>
												}
												
												<div className={'flex-1'.classNames()}>
													<a href={content_url} target='_blank' className={"d-block font-size-14 font-weight-600".classNames()}>
														{content_title}
													</a>
													<div className={'actions'.classNames(style) + 'd-flex align-items-center column-gap-10 margin-top-10'.classNames()}>
														<Link 
															className={'action'.classNames(style) + 'cursor-pointer d-inline-flex align-items-center column-gap-8'.classNames()} 
															title={__('Edit')}
															to={getDashboardPath(`inventory/${content_type}/editor/${content_id}/`)}
														>
															<i className={'ch-icon ch-icon-edit-2 font-size-15'.classNames()}></i> {__('Edit')}
														</Link>
														<span className={'color-text-lighter'.classNames()}>|</span>
														<span
															className={'action'.classNames(style) + 'cursor-pointer d-inline-flex align-items-center column-gap-8'.classNames()}
															title={__('Delete')}
															onClick={()=>deleteContent(content_id)}
														>
															<i className={'ch-icon ch-icon-trash color-error font-size-15'.classNames()}></i> {__('Delete')}
														</span>
													</div>
												</div>
											</div>
										</td>
										<td data-th={__('Category')}>
											{category_name || <>&nbsp;</>}
										</td>
										<td data-th={__('Monetization')}>
											{product_id ? __('Paid') : __('Free')}
										</td>
										<td data-th={__('Status')}>
											<div>
												{content_status}
											</div>
										</td>
										<td data-th={__('Created')}>
											{formatDate(created_at, window[data_pointer]?.date_format + ' ' + window[data_pointer]?.time_format)}
										</td>
									</tr>
								})
							}
							<TableStat 
								empty={!state.contents.length} 
								loading={state.fetching}/>
						</tbody>
					</table>
					{
						(state.segmentation?.page_count || 0) < 2 ? null :
						<>
							<br/>
							<div className={'d-flex justify-content-end'.classNames()}>
								<Pagination
									onChange={(page) => setFilter('page', page)}
									pageNumber={filterState.page}
									pageCount={state.segmentation.page_count}
								/>
							</div>
						</>
					}
				</>
		}
	</InventoryWrapper>
}
