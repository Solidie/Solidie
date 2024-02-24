import React, { useContext, useEffect, useMemo, useState } from "react";

import {__, data_pointer, isEmpty} from 'crewhrm-materials/helpers.jsx'
import {TextField} from 'crewhrm-materials/text-field/text-field.jsx';
import {TextEditor} from 'crewhrm-materials/text-editor/text-editor.jsx';
import { DropDown } from "crewhrm-materials/dropdown/dropdown.jsx";
import { request } from "crewhrm-materials/request.jsx";
import { InitState } from "crewhrm-materials/init-state.jsx";
import { ContextToast } from "crewhrm-materials/toast/toast.jsx";
import { LoadingIcon } from "crewhrm-materials/loading-icon/loading-icon";

const {readonly_mode} = window[data_pointer];

function findParentLessonId(lessonId, lessons) {

    for (let lesson of lessons) {
        if (lesson.lesson_id === lessonId) {
            // If the current lesson matches the provided lessonId,
            // return undefined since it is the root level lesson
            return undefined;
        }

        if (lesson.children) {
            for (let child of lesson.children) {
                if (child.lesson_id === lessonId) {
                    // If the current child matches the provided lessonId,
                    // return the parent lesson's ID
                    return lesson.lesson_id;
                }

                if (child.children) {
                    // If the current child has children, recursively search
                    const parent = findParentLessonId(lessonId, [child]);
                    if (parent) {
                        return parent;
                    }
                }
            }
        }
    }

    // Return null if the lessonId is not found
    return null;
}

function flattenLessonsExcludingId(lessonId, lessons, level = 0) {

    let flattenedLessons = [];

    for (let lesson of lessons) {
        if (lesson.lesson_id !== lessonId) {
			
            // Create an indented text based on the current level
            let indentation = "—".repeat(level);

            // Add the lesson object to the flattened array with indentation
            flattenedLessons.push({
				id: lesson.lesson_id, 
				label: `${indentation}${lesson.lesson_title}`
			});

            if (lesson.children) {
                // Recursively call the function for children lessons
				const child_levels = flattenLessonsExcludingId(lessonId, lesson.children, level + 1);
                flattenedLessons = [...flattenedLessons, ...child_levels];
            }
        }
    }

    return flattenedLessons;
}

export function LessonEditor({content_id, lesson_id, lessons=[]}) {

	const {ajaxToast} = useContext(ContextToast);

	const parent_options = useMemo(()=>flattenLessonsExcludingId(lesson_id, lessons), [lesson_id, lessons]);
	const parent_id = useMemo(()=>findParentLessonId(lesson_id, lessons), [lesson_id, lessons]);

	const [state, setState] = useState({
		fetching: true,
		saving: false,
		saving_slug: false,
		has_changes: false,
		error_message: null,
		slug_editor: false,
		values: {
			lesson_title: '',
			lesson_content: '',
			lesson_slug: '',
			parent_id
		}
	});
	
	const dispatchChange=(name, value)=>{
		setState({
			...state,
			has_changes: true,
			values: {
				...state.values,
				[name]: value
			}
		});
	}

	const publishLesson=()=>{
		setState({
			...state,
			saving: true
		});


		const payload = {
			...state.values, 
			kses_lesson_content: state.values.lesson_content, 
			content_id,
			lesson_id
		}

		request('updateLessonSingle', payload, resp=>{
			
			ajaxToast(resp);

			setState({
				...state,
				saving: false
			});
		});
	}

	const fetchLesson=()=>{

		setState({
			...state,
			fetching: true
		});

		request('fetchLessonForEditor', {lesson_id, content_id, is_editor: true}, resp=>{

			const {
				success, 
				data:{ 
					lesson, 
					message=__('Something went wrong!') 
				}
			} = resp;

			setState({
				...state,
				fetching: false,
				error_message: !success ? message : null,
				values: success ? lesson : state.values
			});
		});
	}

	const updateSlug=()=>{

		setState({
			...state,
			saving_slug: true
		});

		const {lesson_slug} = state.values;

		request('updateLessonSlug', {lesson_slug, lesson_id, content_id}, resp=>{
			const {
				success,
				data: {
					lesson_slug,
					lesson_permalink
				}
			} = resp;

			ajaxToast(resp);

			setState({
				...state,
				saving_slug: false,
				slug_editor: false,
				values: {
					...state.values,
					lesson_slug: success ? lesson_slug : state.values.lesson_slug,
					lesson_permalink: success ? lesson_permalink : state.values.lesson_permalink
				}
			});
		});
	}

	useEffect(()=>{
		fetchLesson();
	}, [lesson_id]);

	if ( state.fetching || state.error_message ) {
		return <InitState 
			fetching={state.fetching} 
			error_message={state.error_message}
		/>
	}

	const {lesson_permalink=''} = state.values || {};
	const slug_parent = lesson_permalink.slice(0, lesson_permalink.lastIndexOf('/', lesson_permalink.lastIndexOf('/') - 1 ));
	
	return <div>
		
		<div className={'margin-bottom-15 d-flex column-gap-15'.classNames()}>
			<div className={'flex-1'.classNames()}>
				<strong className={'d-block font-weight-600'.classNames()}>
					{__('Lesson Title')}
				</strong>
				<TextField
					value={state.values.lesson_title || ''}
					onChange={v=>dispatchChange('lesson_title', v)}
				/>
				<div 
					className={'d-inline-flex align-items-center flex-wrap-wrap flex-direction-row column-gap-5'.classNames()}
					style={{margin: '3px 0 15px', height: '34px'}}
				>
					<a 
						href={state.values.lesson_permalink} 
						target='_blank'
					>
						{slug_parent}/{state.slug_editor ? null : <><strong>{state.values.lesson_slug}</strong>/</>}
					</a>

					{
						!state.slug_editor ? 
							<i 
								className={'ch-icon ch-icon-edit-2 cursor-pointer font-size-18'.classNames()}
								onClick={()=>setState({...state, slug_editor: true})}></i>
							:
							<>
								<TextField 
									style={{width: '170px', height: '30px', padding: '0 9px'}}
									value={state.values.lesson_slug}
									autofocus={true}
									onChange={v=>dispatchChange('lesson_slug', v)}
								/>
								<button 
									className={'button button-primary button-outlined button-small'.classNames()}
									onClick={updateSlug}
									disabled={readonly_mode || state.saving || state.saving_slug || isEmpty((state.values.lesson_slug || '').trim())}
								>
									{__('Update')} <LoadingIcon show={state.saving_slug}/>
								</button>
							</>
					}
				</div>
			</div>
			{
				!parent_options.length ? null :
				<div style={{width: '220px'}}>
					<strong className={'d-block font-weight-600'.classNames()}>
						{__('Parent Lesson')}
					</strong>
					<DropDown 
						value={state.values.parent_id}
						options={parent_options}
						onChange={v=>dispatchChange('parent_id', v)}
					/>
				</div>
			}
		</div>

		<div className={'margin-bottom-15'.classNames()}>
			<strong className={'d-block font-weight-600'.classNames()}>
				{__('Lesson Content')}
			</strong>
			<TextEditor
				value={state.values.lesson_content || ''}
				onChange={v=>dispatchChange('lesson_content', v)}
			/>
		</div>

		<div className={'text-align-right'.classNames()}>
			<button 
				className={'button button-primary button-small'.classNames()}
				onClick={publishLesson}
				disabled={readonly_mode || state.saving || state.saving_slug || !state.has_changes || isEmpty( state.values.lesson_title ) || isEmpty( state.values.lesson_content )}
			>
				{__('Publish')} <LoadingIcon show={state.saving}/>
			</button>
		</div>
	</div>
}
