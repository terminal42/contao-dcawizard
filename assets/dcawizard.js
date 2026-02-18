import './dcawizard.scss';

import { Application } from '@hotwired/stimulus';
import GroupWidgetController from './controller';

const application = Application.start();
application.register('terminal42--dcawizard', GroupWidgetController);
