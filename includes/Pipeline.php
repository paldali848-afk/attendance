<?php
class Pipeline {
    protected $stages = [];
    public function pipe(callable $stage) {
        $this->stages[] = $stage;
        return $this;
    }
    public function process($payload) {
        foreach ($this->stages as $stage) {
            $payload = $stage($payload);
        }
        return $payload;
    }
}