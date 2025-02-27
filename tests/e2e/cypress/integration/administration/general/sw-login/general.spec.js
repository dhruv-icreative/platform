// / <reference types="Cypress" />

describe('User: Test general user values', () => {
    beforeEach(() => {
        cy.clearCookies();
        cy.clearCookie('bearerAuth')
        cy.clearCookie('refreshBearerAuth')
        cy.visit(`${Cypress.env('admin')}`)
    });

    it('@general: check if user title is set correctly', () => {
        cy.login('admin');
        cy.get('.sw-admin-menu__user-type').contains('Administrator');
    });
});
