import { Check, Radio } from 'lucide-react';

import { steps } from './constants';

export function SetupProgress({
  currentStep,
  progress,
  onStepChange,
  isDisabled = false,
}: {
  currentStep: number;
  progress: string;
  onStepChange: (step: number) => void;
  isDisabled?: boolean;
}) {
  return (
    <aside className="self-start border border-[#2a2a2a] bg-[#111111] p-5">
      <div className="mb-5 flex h-14 w-14 items-center justify-center bg-primary text-white">
        <Radio size={24} />
      </div>
      <p className="text-sm text-[#888888]">Setup Progress</p>
      <p className="mt-1 text-4xl text-white" style={{ fontFamily: 'var(--font-heading)' }}>
        {progress}
      </p>
      <div className="mt-6 grid gap-2">
        {steps.map((step, index) => {
          const StepIcon = step.icon;
          const isActive = index === currentStep;
          const isComplete = index < currentStep;

          return (
            <button
              key={step.label}
              type="button"
              disabled={isDisabled}
              onClick={() => onStepChange(index)}
              className={`flex h-12 items-center justify-between border px-3 text-left transition-colors ${
                isActive
                  ? 'border-primary bg-primary/10 text-white'
                  : 'border-[#333333] text-[#aaaaaa] hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-50'
              }`}
            >
              <span className="inline-flex items-center gap-3 text-sm font-semibold">
                <StepIcon size={16} />
                {step.label}
              </span>
              {isComplete && <Check size={16} className="text-primary" />}
            </button>
          );
        })}
      </div>
    </aside>
  );
}
