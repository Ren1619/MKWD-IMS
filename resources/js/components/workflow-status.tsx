import { Check, Circle } from 'lucide-react';
import { cn } from '@/lib/utils';

type WorkflowStatusProps = {
    steps: readonly string[];
    current: string;
    terminal?: readonly string[];
};

function label(value: string): string {
    return value.replaceAll('_', ' ');
}

export function WorkflowStatus({
    steps,
    current,
    terminal = [],
}: WorkflowStatusProps) {
    const currentIndex = steps.indexOf(current);
    const isTerminal = terminal.includes(current);
    const isAlternateState = currentIndex === -1 && !isTerminal;

    return (
        <div aria-label="Workflow progress" className="overflow-x-auto pb-1">
            <ol className="flex min-w-max items-start gap-1">
                {steps.map((step, index) => {
                    const isComplete = !isTerminal && index < currentIndex;
                    const isCurrent = step === current;

                    return (
                        <li
                            key={step}
                            aria-current={isCurrent ? 'step' : undefined}
                            className="flex items-center"
                        >
                            <div
                                className={cn(
                                    'flex min-h-11 items-center gap-2 rounded-lg border px-3 py-2 text-xs capitalize',
                                    isCurrent &&
                                        'border-primary bg-primary/10 font-medium text-primary',
                                    isComplete &&
                                        'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
                                    !isCurrent &&
                                        !isComplete &&
                                        'text-muted-foreground',
                                )}
                            >
                                {isComplete ? (
                                    <Check className="size-3.5" />
                                ) : (
                                    <Circle className="size-3.5" />
                                )}
                                {label(step)}
                            </div>
                            {index < steps.length - 1 && (
                                <span
                                    aria-hidden="true"
                                    className="mx-1 h-px w-4 bg-border"
                                />
                            )}
                        </li>
                    );
                })}
                {(isTerminal || isAlternateState) && (
                    <li
                        aria-current="step"
                        className={cn(
                            'flex min-h-11 items-center rounded-lg border px-3 py-2 text-xs font-medium capitalize',
                            isTerminal
                                ? 'border-destructive/40 bg-destructive/5 text-destructive'
                                : 'border-primary bg-primary/10 text-primary',
                        )}
                    >
                        {label(current)}
                    </li>
                )}
            </ol>
        </div>
    );
}
