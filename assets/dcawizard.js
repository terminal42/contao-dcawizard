import './dcawizard.scss';

import { Application } from '@hotwired/stimulus';
import WidgetController from './controller';

const application = Application.start();
application.register('terminal42--dcawizard', WidgetController);
