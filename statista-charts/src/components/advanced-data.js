/**
 * Everything beyond the quick entry field: templates, the multi-series table
 * and CSV import. Hidden until someone asks for it.
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import CsvImport from './csv-import';
import DataTable from './data-table';
import { getTemplates } from '../lib/templates';

export default function AdvancedData( { attributes, setAttributes, isOpen, onToggle } ) {
	const { labels, series, type } = attributes;

	return (
		<div className={ `sc-advanced${ isOpen ? ' is-open' : '' }` }>
			<Button
				className="sc-advanced__toggle"
				variant="link"
				icon={ isOpen ? 'arrow-up-alt2' : 'arrow-down-alt2' }
				aria-expanded={ isOpen }
				onClick={ onToggle }
			>
				{ __( 'Advanced options', 'statista-charts' ) }
			</Button>

			{ isOpen && (
				<div className="sc-advanced__body">
					<section className="sc-advanced__section">
						<h4 className="sc-advanced__heading">{ __( 'Start from a template', 'statista-charts' ) }</h4>
						<div className="sc-advanced__templates">
							{ getTemplates().map( ( template ) => (
								<Button
									key={ template.type }
									variant={ type === template.type ? 'primary' : 'secondary' }
									size="compact"
									onClick={ () => setAttributes( template.attributes ) }
								>
									{ template.name }
								</Button>
							) ) }
						</div>
					</section>

					{ type !== 'stat' && (
						<section className="sc-advanced__section">
							<h4 className="sc-advanced__heading">{ __( 'Table with several series', 'statista-charts' ) }</h4>
							<DataTable labels={ labels } series={ series } onChange={ setAttributes } />
						</section>
					) }

					<section className="sc-advanced__section">
						<h4 className="sc-advanced__heading">{ __( 'Import from CSV', 'statista-charts' ) }</h4>
						<CsvImport labels={ labels } series={ series } onImport={ setAttributes } />
					</section>
				</div>
			) }
		</div>
	);
}
