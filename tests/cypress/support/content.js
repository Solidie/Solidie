Cypress.Commands.add('createContent', (content, fe_dashboard) => {

	const {
		content_type,
		content_title,
		content_description,
		monetization
	} = content;

	const url = fe_dashboard ? `my-dashboard/inventory/${content_type}/editor/new/` : `wp-admin/admin.php?page=solidie-inventory#/inventory/${content_type}/editor/new`;
	cy.visit(url);
	cy.reload({ forceReload: true });

	// Set content title
	const ttl_field = cy.get('[data-cylector="content-input-wrapper-content_title"] input');
	ttl_field.clear().type(content_title);
	ttl_field.blur();
	cy.wait(2000);

	// Set thumbnail
	cy.get('[data-cylector="content-input-wrapper-thumbnail"] input').attachFile('../assets/book.jpg');
	cy.wait(1000);

	// Set monetization
	cy.get(`[name="monetization"]`).check(monetization);

	// To Do: Set a category

	// To Do: Modify pricing info
	if (monetization=='paid') {
		
	}

	cy.get('[data-cylector="content-save"]').click();
	cy.get('.solidie-swal button').contains('Yes').click();
	cy.wait(1500);
});
